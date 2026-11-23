<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Amc\AmcReturnRequest;
use App\Models\ActivityLog;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcReturn;
use App\Models\Amc\AmcType;
use App\Services\Amc\AmcReturnService;
use App\Support\Csv;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** AMC › Return & Replace (21/11/2026): list paged, searched and filtered on the server (rule P1), the form, Download CSV. */
class AmcReturnController extends Controller
{
    public function __construct(private readonly AmcReturnService $returns)
    {
    }

    public function index(Request $request): View
    {
        $f = $this->filters($request);
        $query = $this->filtered($f)->with(['client:id,name', 'gardener:id,name', 'items.plant:id,name', 'items.planter:id,name']);
        [$sort, $dir] = ListQuery::sort($query, $request, ['request' => 'request_date', 'added' => 'id'], 'desc');

        return view('amc.returns.index', $f + [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $df = config('nursery.date_format');
        // (23/11/2026) one CSV row per row of the entry, the entry's own fields repeated
        $rows = $this->filtered($this->filters($request))->with(['client:id,name', 'reportedBy:id,name', 'type:id,name', 'gardener:id,name', 'items.planter:id,name', 'items.plant:id,name'])
            ->lazyById(1000)->flatMap(fn (AmcReturn $r) => ($r->items->isEmpty() ? collect([null]) : $r->items)->map(fn ($i) => [
                $r->number(), $r->client?->name, $r->location_1, $i?->location_2, $r->request_date?->format($df), $r->replace_date?->format($df),
                $r->reportedBy?->name, $r->type?->name, $i?->planter?->name, $i?->planter_qty, $i?->plant?->name, $i?->plant_size, $i?->plant_qty,
                $i?->rate !== null ? Money::indian($i->rate, 2) : '', $i?->amount() !== null ? Money::indian($i->amount(), 2) : '',
                $r->gardener?->name, $r->return_remark, $i?->quality ?? $r->quality,
            ]));

        return Csv::download('amc-return-replace-'.now()->format('Y-m-d').'.csv', ['Entry no', 'Customer', 'Location', 'Location 2', 'Request date', 'Replace date',
            'Reported by', 'AMC type', 'Planter', 'Planter qty', 'Plant', 'Plant size', 'Plant qty', 'Rate', 'Amount', 'Gardener', 'Return remark', 'Quality'], $rows);
    }

    public function create(): View
    {
        return $this->form(new AmcReturn(['request_date' => today()]));
    }

    public function edit(AmcReturn $return): View
    {
        return $this->form($return);
    }

    public function store(AmcReturnRequest $request): RedirectResponse
    {
        $r = $this->returns->save(null, $request->forDb(), $request->user());

        return redirect()->route('amc.returns.edit', $r)->with('toast', 'Return & Replace '.$r->number().' added');
    }

    public function update(AmcReturnRequest $request, AmcReturn $return): RedirectResponse
    {
        $this->returns->save($return, $request->forDb(), $request->user());

        return redirect()->route('amc.returns.edit', $return)->with('toast', 'Return & Replace '.$return->number().' saved');
    }

    public function destroy(Request $request, AmcReturn $return): RedirectResponse
    {
        $this->returns->delete($return, $request->user());

        return redirect()->route('amc.returns.index')->with('toast', 'Return & Replace '.$return->number().' deleted');
    }

    private function filters(Request $request): array
    {
        return [
            'q' => LookupController::term($request),
            'quality' => in_array($request->query('quality'), config('amc.qualities'), true) ? $request->query('quality') : null,
        ];
    }

    private function filtered(array $f)
    {
        return AmcReturn::query()
            ->when($f['q'] !== '', fn ($w) => $w->whereIn('amc_client_id', AmcClient::select('id')->where('name', 'like', LookupController::prefix($f['q']))))
            // (23/11/2026) quality is on the rows: an entry with a row of that quality
            ->when($f['quality'], fn ($w) => $w->whereHas('items', fn ($i) => $i->where('quality', $f['quality'])));
    }

    private function form(AmcReturn $r): View
    {
        $r->loadMissing(['creator', 'updater']);
        $keep = fn ($f) => old($f, $r->{$f});
        $client = $keep('amc_client_id') ? AmcClient::with(['lines.planter:id,name', 'lines.plant:id,name,size,rate'])->find($keep('amc_client_id')) : null;
        // (23/11/2026) the rows: as posted after a refused save, else the entry's own. Posted rows keep their keys
        // (items[0], items[2] after a row was taken out) so each row's errors (items.2.plant_qty) show on that row.
        // A refused save with every row taken out posts no rows: keep them out (old input has the customer).
        $items = old('items') !== null || old('amc_client_id') !== null ? array_filter((array) old('items'), 'is_array') : ($r->exists ? $r->items()->get()->map(fn ($i) => [
            'amc_client_line_id' => $i->amc_client_line_id, 'location_2' => $i->location_2, 'amc_planter_id' => $i->amc_planter_id, 'planter_qty' => $i->planter_qty,
            'amc_plant_id' => $i->amc_plant_id, 'plant_size' => $i->plant_size, 'plant_qty' => $i->plant_qty,
            'quality' => $i->quality,
        ])->all() : []);
        // (24/11/2026) a saved row shows (and keeps) its own plant size and rate; also after a refused save
        if ($r->exists) {
            $saved = $r->items()->get()->keyBy(fn ($i) => AmcReturnService::keptKey($i->amc_client_line_id, $i->amc_plant_id));
            $lineOf = ($client?->lines ?? collect())->keyBy('id');
            $items = array_map(function ($it) use ($saved, $lineOf) {
                $line = $lineOf->get((int) ($it['amc_client_line_id'] ?? 0));
                $old = $saved->get(AmcReturnService::keptKey($line?->id, $line ? $line->amc_plant_id : ($it['amc_plant_id'] ?? null)));

                return $old && $old->amc_plant_id ? $it + ['saved_rate' => $old->rate, 'saved_size' => $old->plant_size, 'saved' => true] : $it;
            }, $items);
        }
        // the names, sizes and rates shown on the rows: of the entries' and the rows' own planters and plants
        $lines = $client?->lines ?? collect();
        $planterIds = array_merge(array_filter(array_column($items, 'amc_planter_id')), $lines->pluck('amc_planter_id')->filter()->all());
        $plantIds = array_merge(array_filter(array_column($items, 'amc_plant_id')), $lines->pluck('amc_plant_id')->filter()->all());
        $people = fn (?string $role, $selected) => AmcLookupController::options(AmcEmployee::forPicker($role, $selected), $selected, fn ($e) => $e->label());

        return view('amc.returns.form', [
            'r' => $r,
            'client' => $client,
            // (23/11/2026) what has been replaced so far, per entry
            'replaced' => $client ? \App\Services\Amc\AmcReturnService::replaceStatus($client->id, $r->id) : ['' => []],
            // (24/11/2026, e2e fix) an entry added again after its saved row was taken out shows the row's saved size and rate
            'savedRows' => AmcReturnService::savedRows($r->id, $client?->id),
            'clients' => AmcLookupController::options(AmcClient::query()->orderBy('name')->orderBy('id'), $keep('amc_client_id'), fn ($m) => $m->name),
            'types' => AmcType::forPicker($keep('amc_type_id'))->get(),
            'items' => $items,
            'planters' => \App\Models\Amc\AmcPlanter::whereIn('id', $planterIds)->get(['id', 'name'])->keyBy('id'),
            'plants' => AmcPlant::whereIn('id', $plantIds)->get(['id', 'name', 'size', 'rate'])->keyBy('id'),
            'reporters' => $people(null, $keep('reported_by_id')),
            'gardeners' => $people(AmcEmployee::GARDENER, $keep('gardener_id')),
            'history' => ActivityLog::history($r),
        ]);
    }
}
