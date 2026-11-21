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
        $query = $this->filtered($f)->with(['client:id,name', 'plant:id,name', 'gardener:id,name']);
        [$sort, $dir] = ListQuery::sort($query, $request, ['request' => 'request_date', 'added' => 'id'], 'desc');

        return view('amc.returns.index', $f + [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $df = config('nursery.date_format');
        $rows = $this->filtered($this->filters($request))->with(['client:id,name', 'reportedBy:id,name', 'type:id,name', 'plant:id,name', 'gardener:id,name'])
            ->lazyById(1000)->map(fn (AmcReturn $r) => [
                $r->number(), $r->client?->name, $r->location_1, $r->location_2, $r->request_date?->format($df), $r->replace_date?->format($df),
                $r->reportedBy?->name, $r->type?->name, $r->plant?->name, $r->plant_size, $r->qty, $r->rate !== null ? Money::indian($r->rate, 2) : '',
                $r->gardener?->name, $r->return_remark, $r->quality,
            ]);

        return Csv::download('amc-return-replace-'.now()->format('Y-m-d').'.csv', ['Entry no', 'Customer', 'Location 1', 'Location 2', 'Request date', 'Replace date',
            'Reported by', 'AMC type', 'Plant', 'Plant size', 'Qty', 'Rate', 'Gardener', 'Return remark', 'Quality'], $rows);
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
            ->when($f['quality'], fn ($w) => $w->where('quality', $f['quality']));
    }

    private function form(AmcReturn $r): View
    {
        $r->loadMissing(['creator', 'updater']);
        $keep = fn ($f) => old($f, $r->{$f});
        $client = $keep('amc_client_id') ? AmcClient::with('lines')->find($keep('amc_client_id')) : null;
        $people = fn (?string $role, $selected) => AmcLookupController::options(AmcEmployee::forPicker($role, $selected), $selected, fn ($e) => $e->label());

        return view('amc.returns.form', [
            'r' => $r,
            'client' => $client,
            'clients' => AmcLookupController::options(AmcClient::query()->orderBy('name')->orderBy('id'), $keep('amc_client_id'), fn ($m) => $m->name),
            'types' => AmcType::forPicker($keep('amc_type_id'))->get(),
            'plants' => AmcPlant::forPicker($keep('amc_plant_id'))->get(),
            'reporters' => $people(null, $keep('reported_by_id')),
            'gardeners' => $people(AmcEmployee::GARDENER, $keep('gardener_id')),
            'history' => ActivityLog::history($r),
        ]);
    }
}
