<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Purchase\GrnActionRequest;
use App\Http\Requests\Purchase\GrnRequest;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\Transporter;
use App\Models\Unit;
use App\Services\GrnService;
use App\Services\PurchaseOrderService;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
| Purchase › GRN entry (PURCHASE tab, prototype screen "grn", built 27/10/2026): one supplier invoice as a full form
| with the entry bar, the short view and "Show all headings", item lines against open PO lines, the amount match box
| (GRN no and date, or "Pending match"), the quality bar, and a list paged on the server (P1). The prototype's page
| capability table gives GRN no Import, CSV or PDF. GRNs are for the unit and financial year on the top bar.
*/
class GrnController extends Controller
{
    public function __construct(private readonly GrnService $grns)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('purchase.grns.show', (int) $request->query('id'));
        }

        return $this->page($request, self::search($request)->first() ?? $this->blank());
    }

    public function create(Request $request): View
    {
        return $this->page($request, $this->blank());
    }

    public function show(Request $request, Grn $grn): View
    {
        $this->authorizeUnit($grn);

        return $this->page($request, $grn);
    }

    public function store(GrnRequest $request): RedirectResponse
    {
        $grn = $this->grns->create($request->validated(), $request->user(), $this->unit(), WorkContext::financialYear(), $request->input('intent') === 'match');

        return redirect()->route('purchase.grns.show', $grn)->with('toast', $this->savedToast($grn));
    }

    public function update(GrnRequest $request, Grn $grn): RedirectResponse
    {
        $this->authorizeUnit($grn);
        $grn = $this->grns->update($grn, $request->validated(), $request->user(), $request->input('intent') === 'match');

        return redirect()->route('purchase.grns.show', $grn)->with('toast', $this->savedToast($grn));
    }

    public function destroy(Request $request, Grn $grn): RedirectResponse
    {
        $this->authorizeUnit($grn);
        $this->grns->delete($grn, $request->user());

        return redirect()->route('purchase.grns.index')->with('toast', 'GRN for invoice '.$grn->invoice_no.' deleted');
    }

    public function quality(GrnActionRequest $request, Grn $grn): RedirectResponse
    {
        $this->authorizeUnit($grn);
        $grn = $this->grns->quality($grn, $request->user(), $request->validated('result'), $request->validated('remarks'), (array) $request->validated('rejected', []));

        return redirect()->route('purchase.grns.show', $grn)->with('toast', $grn->grn_no.': quality '.strtolower($grn->quality_status).($grn->stock_posted ? '; accepted stock received' : ''));
    }

    /** Cancel needs GRN entry · Delete; once quality is approved or rejected (stock may have come in) also the quality right, checked under lock by the service. */
    public function cancel(GrnActionRequest $request, Grn $grn): RedirectResponse
    {
        $this->authorizeUnit($grn);
        $this->grns->cancel($grn, $request->user(), (string) $request->validated('remarks'));

        return redirect()->route('purchase.grns.show', $grn)->with('toast', $grn->grn_no.' cancelled');
    }

    /** GRN list: all, pending match, pending quality, approved. Paged, searched and sorted on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'match' => 'Pending match', 'quality' => 'Pending quality', 'approved' => 'Approved'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = self::search($request)->reorder()->with('currency:id,code');
        match ($view) {
            'match' => $query->pendingMatch(),
            'quality' => $query->pendingQuality(),
            'approved' => $query->qualityApproved(),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'invoice_date', 'no' => 'grn_no', 'amount' => 'invoice_amount'], 'desc');

        return view('purchase.grns.list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => LookupController::term($request), 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json(self::search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (Grn $g) => [
            'id' => $g->id,
            'label' => ($g->grn_no ?? 'Pending match').' · '.($g->party?->name ?? '').' · '.$g->invoice_no,
            'sub' => $g->invoice_date?->format(config('nursery.date_format')).' · '.$g->stateLabel(),
            'url' => route('purchase.grns.show', $g),
        ]));
    }

    /**
     * Open POs of a supplier in the unit on the top bar (Approved or Partly received, something pending) with their
     * pending lines, for the PO no and item drop-downs of the lines: [{id, no, lines: [{id, item, label, pending, rate, uom, c, s, i}]}].
     */
    public function orders(Request $request): JsonResponse
    {
        $party = $request->integer('party');
        $grn = $request->integer('grn') ? Grn::find($request->integer('grn')) : null;
        if ($grn) {
            $this->authorizeUnit($grn);
        }

        return response()->json($party ? self::openOrders($party, $grn?->unit_id ?? WorkContext::unit()?->id, $grn) : []);
    }

    /** Sales orders of a customer (unit on the top bar) for the SO no drop-down. */
    public function salesOrders(Request $request): JsonResponse
    {
        $customer = $request->integer('customer');

        return response()->json($customer ? self::customerOrders($customer, WorkContext::unit()?->id)->map(fn ($s) => ['id' => $s->id, 'label' => $s->so_no.' · '.$s->po_number])->values() : []);
    }

    /**
     * A supplier's active purchase items (and items of no party) for the item drop-downs of credit / debit notes and lines
     * without a PO: [{id, label, rate, uom, c, s, i}], at most 200 in item no order.
     */
    public function supplierItems(Request $request): JsonResponse
    {
        $party = $request->integer('party');
        if (! $party) {
            return response()->json([]);
        }

        return response()->json(\App\Models\Item::forPurchase()->with(['uom:id,code', 'taxStructure'])
            ->where(fn ($q) => $q->whereNull('party_id')->orWhere('party_id', $party))->orderBy('item_no')->orderBy('id')->limit(200)->get()
            ->map(fn (\App\Models\Item $i) => ['id' => $i->id, 'label' => $i->pickerLabel(), 'rate' => (float) $i->price, 'uom' => $i->uom?->code,
                'c' => (float) $i->taxStructure?->cgst_rate, 's' => (float) $i->taxStructure?->sgst_rate, 'i' => (float) $i->taxStructure?->igst_rate]));
    }

    /** Supplier picked: its GST split and currency, for the totals while typing. */
    public function partyInfo(Party $party): JsonResponse
    {
        abort_unless(in_array($party->type, ['Supplier', 'Both'], true), 404);
        $party->loadMissing('currency');

        return response()->json(['supply' => PurchaseOrderService::supplyType($party), 'currency' => $party->currency?->code ?? 'INR']);
    }

    /** POs open for receipt (and those already on this GRN) with their pending lines and what the page needs per line. */
    public static function openOrders(int $partyId, ?int $unitId, ?Grn $grn = null): array
    {
        $onGrn = $grn ? GrnLine::where('grn_id', $grn->id)->whereNotNull('purchase_order_id')->get(['purchase_order_id', 'purchase_order_line_id', 'quantity']) : collect();
        $held = ($grn && $grn->isMatched() && $grn->status === Grn::SAVED) ? $onGrn->groupBy('purchase_order_line_id')->map(fn ($g) => (float) $g->sum('quantity')) : collect();
        $pos = PurchaseOrder::query()->where('unit_id', $unitId)->where('party_id', $partyId)
            ->where(fn ($q) => $q->pendingReceipt()->orWhereIn('id', $onGrn->pluck('purchase_order_id')->all()))
            ->with(['lines' => fn ($l) => $l->with(['item:id,item_no,description', 'uom:id,code', 'taxStructure'])])
            ->orderBy('id')->limit(200)->get(['id', 'po_no', 'po_date', 'status']);

        return $pos->map(fn (PurchaseOrder $po) => [
            'id' => $po->id,
            'no' => $po->po_no,
            'lines' => $po->lines->filter(fn ($l) => $l->pending() > 0 || $held->has($l->id))->map(fn (PurchaseOrderLine $l) => [
                'id' => $l->id,
                'item' => $l->item_id,
                'label' => ($l->item?->item_no ?? '?').' · '.($l->description ?: $l->item?->description),
                'pending' => round($l->pending() + (float) ($held[$l->id] ?? 0), 3),
                'rate' => round((float) $l->rate * (100 - (float) $l->discount_percent) / 100, 4),
                'uom' => $l->uom?->code,
                'c' => (float) $l->taxStructure?->cgst_rate, 's' => (float) $l->taxStructure?->sgst_rate, 'i' => (float) $l->taxStructure?->igst_rate,
            ])->values()->all(),
        ])->filter(fn ($p) => $p['lines'])->values()->all();
    }

    public static function customerOrders(int $customerId, ?int $unitId)
    {
        return SalesOrder::where('party_id', $customerId)->when($unitId, fn ($q) => $q->where('unit_id', $unitId))->latest('id')->limit(200)->get(['id', 'so_no', 'po_number']);
    }

    /** GRNs of the unit and year on the top bar, newest first; ?q= searches GRN no, supplier invoice no or supplier name. */
    public static function search(Request $request)
    {
        $q = LookupController::term($request);

        return Grn::query()->forWork(WorkContext::unit()?->id, WorkContext::financialYear()->id)
            ->with('party:id,name')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('grn_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('invoice_no', 'like', LookupController::prefix($q))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))))
            ->latest('id');
    }

    private function page(Request $request, Grn $grn): View
    {
        $grn->loadMissing(['lines.item', 'lines.uom', 'lines.order', 'party.currency', 'currency', 'transporter', 'customer', 'salesOrder', 'creator', 'updater', 'qualityUser']);
        $partyId = (int) old('party_id', $grn->party_id);
        $party = $partyId ? Party::with('currency')->find($partyId) : null;
        $customerId = (int) old('customer_id', $grn->customer_id);
        $unitId = $grn->unit_id ?? WorkContext::unit()?->id;

        return view('purchase.grns.page', [
            'grn' => $grn,
            'entries' => self::search($request)->limit(50)->get(),
            'suppliers' => QuotationController::pickerOptions(Party::suppliers(), Party::class, $partyId ?: null),
            'customers' => QuotationController::pickerOptions(Party::customers(), Party::class, $customerId ?: null),
            'salesOrders' => $customerId ? self::customerOrders($customerId, $unitId) : collect(),
            'orders' => $partyId ? self::openOrders($partyId, $unitId, $grn->exists ? $grn : null) : [],
            'items' => $partyId ? PurchaseOrderController::itemOptions($partyId) : collect(),
            'transporters' => Transporter::forPicker($grn->transporter_id)->orderBy('name')->get(),
            'supply' => $party ? PurchaseOrderService::supplyType($party) : ($grn->supply_type ?: ''),
            'currency' => $party?->currency?->code ?? $grn->currencyCode(),
            'company' => Company::current(),
            'history' => ActivityLog::history($grn),
            'unit' => WorkContext::unit($request->user()),
            'q' => trim((string) $request->query('q')),
            'tolerance' => (float) config('nursery.grn.match_tolerance', 1),
            'withoutPo' => (bool) config('nursery.grn.lines_without_po', false),
        ]);
    }

    private function savedToast(Grn $grn): string
    {
        if (! $grn->isMatched()) {
            return 'Saved as pending match. No GRN no yet: the lines total does not match the invoice amount.';
        }
        if ($grn->isPurchase() && $grn->quality_status === Grn::QA_PENDING) {
            return $grn->grn_no.' saved. Stock is usable after quality approval.';
        }

        return $grn->grn_no.' saved';
    }

    private function blank(): Grn
    {
        $grn = new Grn(['invoice_type' => 'Purchase']);
        $grn->status = Grn::SAVED;
        $grn->match_status = Grn::PENDING_MATCH;
        $grn->quality_status = Grn::QA_PENDING;

        return $grn;
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot enter GRNs.');

        return $unit;
    }

    private function authorizeUnit(Grn $g): void
    {
        abort_unless(in_array($g->unit_id, WorkContext::unitIds(), true), 403);
    }
}
