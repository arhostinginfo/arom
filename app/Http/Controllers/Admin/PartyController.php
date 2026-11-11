<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Admin\PartyRequest;
use App\Http\Requests\Crm\ImportRequest;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Party;
use App\Services\PartyService;
use App\Support\Csv;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Masters › Party master (built with the Sales module, 15/10/2026): list paged, searched and filtered on the
 * server (rule P1), a form, Import and Download CSV (OTHER POINTS row 4: page setup, add / update, import, CSV).
 */
class PartyController extends Controller
{
    public function __construct(private readonly PartyService $parties)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $type = in_array($request->query('type'), Party::TYPES, true) ? $request->query('type') : null;
        $status = in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null;
        $query = Party::query()->with('currency:id,code')
            ->when($type, fn ($w) => $w->where('type', $type))
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))->orWhere('gstin', 'like', LookupController::prefix(strtoupper($q)))));
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name']);

        return view('admin.parties.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'type' => $type, 'status' => $status, 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Party(['type' => 'Customer', 'status' => 'Active', 'opening_balance_type' => 'DR', 'currency_id' => Currency::defaultId()]));
    }

    public function edit(Party $party): View
    {
        return $this->form($party);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $p = $this->parties->save(null, $request->validated(), $request->user());

        return redirect()->route('admin.parties.edit', $p)->with('toast', 'Party '.$p->name.' added');
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        $this->parties->save($party, $request->validated(), $request->user());

        return redirect()->route('admin.parties.edit', $party)->with('toast', 'Party '.$party->name.' saved');
    }

    public function destroy(Request $request, Party $party): RedirectResponse
    {
        $this->parties->delete($party, $request->user());

        return redirect()->route('admin.parties.index')->with('toast', 'Party '.$party->name.' deleted');
    }

    /** Download CSV in the import layout, 1,000 rows at a time, in the order they were added (C13). */
    public function export(): StreamedResponse
    {
        $rows = Party::query()->with('currency:id,code')->lazyById(1000)->map(fn (Party $p) => [
            $p->name, $p->gstin, $p->type, $p->billing_address, $p->shipping_address, $p->payment_terms,
            $p->opening_balance, $p->opening_balance_type, $p->currency?->code, $p->status,
        ]);

        return Csv::download('parties-'.now()->format('Y-m-d').'.csv', array_values(PartyService::COLUMNS), $rows);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('party-import-template.csv', array_values(PartyService::COLUMNS), [
            ['Apex Auto Components', '27AABCA1234F1Z5', 'Customer', 'Plot 12, MIDC Chakan, Pune 410501', 'Gate 2, Plot 12, MIDC Chakan, Pune 410501', '30 days', '0', 'DR', 'INR', 'Active'],
        ]);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Name', 'Type']);
        $count = $this->parties->import($rows, $request->user());

        return redirect()->route('admin.parties.index')->with('toast', $count.' part(ies) imported');
    }

    private function form(Party $party): View
    {
        $party->loadMissing(['creator', 'updater']);

        return view('admin.parties.form', [
            'party' => $party,
            'currencies' => Currency::orderBy('code')->get(),
            'history' => ActivityLog::history($party),
        ]);
    }
}
