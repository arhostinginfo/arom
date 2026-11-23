<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Amc\AmcClientRequest;
use App\Http\Requests\Crm\ImportRequest;
use App\Models\ActivityLog;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcClientLine;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcPlanter;
use App\Models\Amc\AmcVertical;
use App\Services\Amc\AmcClientService;
use App\Support\Csv;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AMC › AMC Client Inventory (21/11/2026): list paged, searched and filtered on the server (rule P1), a form with the
 * location lines, Import (CSV, whole file checked first) and Download CSV (one row per line, the import layout).
 */
class AmcClientController extends Controller
{
    public function __construct(private readonly AmcClientService $clients)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $vertical = $request->integer('vertical') ?: null;
        $query = AmcClient::query()->with(['vertical:id,name', 'gardener:id,name,code', 'activeContract.gardener:id,name,code'])->withCount('lines')
            ->when($vertical, fn ($w) => $w->where('amc_vertical_id', $vertical))
            ->when($q !== '', fn ($w) => $w->where('name', 'like', LookupController::prefix($q)));
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name', 'updated' => 'updated_at']);

        return view('amc.clients.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'vertical' => $vertical, 'sort' => $sort, 'dir' => $dir,
            'verticals' => AmcVertical::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return $this->form(new AmcClient());
    }

    public function edit(AmcClient $client): View
    {
        return $this->form($client);
    }

    public function store(AmcClientRequest $request): RedirectResponse
    {
        $client = $this->clients->save(null, $request->validated(), $request->user());

        return redirect()->route('amc.clients.edit', $client)->with('toast', 'AMC client '.$client->name.' added');
    }

    public function update(AmcClientRequest $request, AmcClient $client): RedirectResponse
    {
        $this->clients->save($client, $request->validated(), $request->user());

        return redirect()->route('amc.clients.edit', $client)->with('toast', 'AMC client '.$client->name.' saved');
    }

    /** One row per location line (a client without lines: one row with the header only), in the import layout. */
    public function export(): StreamedResponse
    {
        $rows = AmcClient::query()->with(['vertical:id,name', 'lines.planter:id,name', 'lines.plant:id,name'])->lazyById(500)
            ->flatMap(fn (AmcClient $c) => $c->lines->isEmpty()
                ? [[$c->name, $c->vertical?->name, '', '', '', '', '', '']]
                : $c->lines->map(fn (AmcClientLine $l) => [$c->name, $c->vertical?->name, $l->location_1, $l->location_2, $l->planter?->name, $l->planter_qty, $l->plant?->name, $l->plant_qty])->all());

        return Csv::download('amc-clients-'.now()->format('Y-m-d').'.csv', array_values(AmcClientService::COLUMNS), $rows);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('amc-client-import-template.csv', array_values(AmcClientService::COLUMNS), [
            ['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 5 - Lobby', 'Ceramic round 12 inch', '10', 'Money Plant', '10'],
            ['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 6 - Cafeteria', '', '', 'Rose', '20'],
            ['Rich Next', 'AMC', 'ASTP', '', '', '', 'Rose', '10'],
        ]);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Customer name', 'Business segment']);
        $count = $this->clients->import($rows, $request->user());

        return redirect()->route('amc.clients.index')->with('toast', $count.' AMC client(s) imported');
    }

    /**
     * A client's details for the contract and Return & Replace pages: segment, location lines and, from its current
     * Active contract, the AMC type and gardener.
     */
    public function info(Request $request, AmcClient $client): JsonResponse
    {
        $client->load(['vertical:id,name', 'gardener:id,name,code,role,status', 'lines.planter:id,name', 'lines.plant:id,name,size', 'activeContract.type:id,name', 'activeContract.gardener:id,name,code']);
        $c = $client->activeContract;
        // (23/11/2026, test fix) the Return & Replace edit page leaves out the return being edited, also when the customer is re-picked
        $done = \App\Services\Amc\AmcReturnService::replaceStatus($client->id, $request->integer('except') ?: null);
        $none = \App\Services\Amc\AmcReturnService::statusOf([]);
        // (22/11/2026, test fix) only an active Gardener is offered to a new contract / Return & Replace: one made
        // Inactive or given another role since would be filled in and then refused on save.
        $g = $client->gardener;
        $g = $g && $g->status === 'Active' && $g->role === AmcEmployee::GARDENER ? $g : null;

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'segment' => $client->vertical?->name,
            // (22/11/2026) the client's own gardener: a new contract starts with them
            'gardener_id' => $g?->id, 'gardener' => $g?->label(),
            'lines' => $client->lines->map(fn (AmcClientLine $l) => [
                'id' => $l->id, 'line_no' => $l->line_no, 'label' => $l->locationLabel(), 'location_1' => $l->location_1, 'location_2' => $l->location_2,
                'planter' => $l->planter?->name, 'planter_qty' => $l->planter_qty, 'plant' => $l->plant?->label(), 'plant_id' => $l->amc_plant_id, 'plant_qty' => $l->plant_qty,
                'replaced' => $done[$l->id] ?? $none,
            ])->values(),
            // (23/11/2026) returns made for a Location without picking an entry, per Location
            'location_returns' => (object) $done[''],
            'contract' => $c ? [
                'id' => $c->id, 'number' => $c->number(), 'amc_type_id' => $c->amc_type_id, 'type' => $c->type?->name,
                'gardener_id' => $c->gardener_id, 'gardener' => $c->gardener?->label(),
            ] : null,
        ]);
    }

    private function form(AmcClient $client): View
    {
        $client->loadMissing(['lines', 'creator', 'updater', 'gardener', 'activeContract.gardener']);
        $gardener = old('gardener_id', $client->gardener_id);
        $party = old('party_id', $client->party_id);
        $parties = AmcClientRequest::customerParties()->where(fn ($w) => $w->whereNotIn('id', AmcClient::query()->whereNotNull('party_id')->select('party_id')))
            ->orderBy('name')->orderBy('id');
        $keepPlanters = $client->lines->pluck('amc_planter_id')->filter()->all();
        $keepPlants = $client->lines->pluck('amc_plant_id')->filter()->all();

        return view('amc.clients.form', [
            'client' => $client,
            'verticals' => AmcVertical::forPicker(old('amc_vertical_id', $client->amc_vertical_id))->get(),
            'planters' => AmcPlanter::query()->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $keepPlanters))->orderBy('name')->get(),
            // (22/11/2026) Customer name: active customers of the Party master not yet in the AMC Client Inventory
            'parties' => AmcLookupController::options($parties, $party, fn ($p) => $p->name),
            'gardeners' => AmcLookupController::options(AmcEmployee::forPicker(AmcEmployee::GARDENER, $gardener), $gardener, fn ($e) => $e->label()),
            'plants' => AmcPlant::query()->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $keepPlants))->orderBy('name')->get(),
            'history' => ActivityLog::history($client),
        ]);
    }
}
