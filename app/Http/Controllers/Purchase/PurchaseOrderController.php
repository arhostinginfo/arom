<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Purchase\PurchaseOrderActionRequest;
use App\Http\Requests\Purchase\PurchaseOrderRequest;
use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\TaxStructure;
use App\Models\Transporter;
use App\Models\Unit;
use App\Models\Uom;
use App\Services\PurchaseOrderService;
use App\Support\Csv;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/*
| Purchase › Purchase order (PURCHASE tab, prototype screen "po", built 27/10/2026): one entry as a full form with
| the entry bar, short view and "Show all headings" (PURCHASE B11 / C11), item lines with UOM and Tax from their
| masters, GST totals, the approval bar (D3), Generate PDF, and a list paged on the server (P1). The prototype's page
| capability table has no Import or Download CSV for this page, so there are none. POs are for the unit and
| financial year on the top bar.
*/
class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('purchase.orders.show', (int) $request->query('id'));
        }

        return $this->page($request, self::search($request)->first() ?? $this->blank());
    }

    public function create(Request $request): View
    {
        return $this->page($request, $this->blank());
    }

    public function show(Request $request, PurchaseOrder $order): View
    {
        $this->authorizeUnit($order);

        return $this->page($request, $order);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->orders->create($request->validated(), $request->user(), $this->unit(), WorkContext::financialYear());

        return redirect()->route('purchase.orders.show', $po)->with('toast', 'Purchase order '.$po->po_no.' added as a Draft');
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $this->orders->update($order, $request->validated(), $request->user());

        return redirect()->route('purchase.orders.show', $order)->with('toast', 'Purchase order '.$order->po_no.' saved');
    }

    public function destroy(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $this->orders->delete($order, $request->user());

        return redirect()->route('purchase.orders.index')->with('toast', 'Purchase order '.$order->po_no.' deleted');
    }

    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $po = $this->orders->submit($order, $request->user());

        return redirect()->route('purchase.orders.show', $order)->with('toast', $po->po_no.' is '.$po->status);
    }

    public function approve(PurchaseOrderActionRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $this->orders->approve($order, $request->user(), $request->validated('remarks'));

        return redirect()->route('purchase.orders.show', $order)->with('toast', $order->po_no.' approved');
    }

    public function reject(PurchaseOrderActionRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $this->orders->reject($order, $request->user(), (string) $request->validated('remarks'));

        return redirect()->route('purchase.orders.show', $order)->with('toast', $order->po_no.' rejected');
    }

    public function cancel(PurchaseOrderActionRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $this->orders->cancel($order, $request->user(), (string) $request->validated('remarks'));

        return redirect()->route('purchase.orders.show', $order)->with('toast', $order->po_no.' cancelled');
    }

    /** Generate PDF: the PO with the company's details and logo, the supplier, items with GST, and the amount in words. */
    public function pdf(PurchaseOrder $order): Response
    {
        $this->authorizeUnit($order);
        $order->load(['lines.item', 'lines.uom', 'party', 'currency', 'transporter', 'unit', 'approver']);

        return Pdf::loadView('pdf.purchase-order', ['po' => $order, 'company' => Company::current()])
            ->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true)
            ->download(str_replace('/', '-', $order->po_no).'.pdf');
    }

    /** Purchase order list: all, pending approval, approved, open (pending GRN), draft or rejected. Paged on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'pending' => 'Pending approval', 'approved' => 'Approved', 'open' => 'Open (pending GRN)', 'draft' => 'Draft or rejected'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = self::search($request)->reorder();
        match ($view) {
            'pending' => $query->pendingApproval(),
            'approved' => $query->where('status', PurchaseOrder::APPROVED),
            'open' => $query->pendingReceipt(),
            'draft' => $query->whereIn('status', [PurchaseOrder::DRAFT, PurchaseOrder::REJECTED]),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'po_date', 'no' => 'po_no', 'total' => 'grand_total'], 'desc');

        return view('purchase.orders.list', [
            'rows' => $query->with('currency:id,code')->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => LookupController::term($request), 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json(self::search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (PurchaseOrder $o) => [
            'id' => $o->id,
            'label' => $o->po_no.' · '.($o->party?->name ?? ''),
            'sub' => Csv::date($o->po_date).' · '.$o->status,
            'url' => route('purchase.orders.show', $o),
        ]));
    }

    /** Suppliers (Supplier or Both, active) for the party picker, first 20 "starts with" matches of name or GST no. */
    public function parties(Request $request): JsonResponse
    {
        $q = LookupController::term($request);

        return response()->json(Party::suppliers()->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))
            ->orWhere('gstin', 'like', LookupController::prefix(strtoupper($q)))))
            ->forPage(LookupController::page(), LookupController::LIMIT)->with('currency:id,code')->get(['id', 'name', 'gstin', 'type', 'currency_id'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => trim($p->type.' '.($p->gstin ?? ''))]));
    }

    /** Supplier picked: its payment terms and currency, the GST split it gets, and the item picker's URL. */
    public function partyInfo(Party $party): JsonResponse
    {
        abort_unless(in_array($party->type, ['Supplier', 'Both'], true), 404);
        $party->loadMissing('currency');

        return response()->json([
            'supply' => PurchaseOrderService::supplyType($party),
            'currency_id' => $party->currency_id ?? Currency::defaultId(),
            'currency' => $party->currency?->code ?? 'INR',
            'payment_terms' => $party->payment_terms,
            'items_url' => route('purchase.items.lookup', ['party' => $party->id]),
        ]);
    }

    /** A supplier's purchase items (and items of no party) for the line pickers, first 20 matching item no, IPN or description. */
    public function items(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $party = $request->integer('party') ?: null;

        return response()->json(Item::forPurchase()->with(['uom:id,code', 'taxStructure', 'currency:id,code'])
            ->where(fn ($w) => $w->whereNull('party_id')->when($party, fn ($p) => $p->orWhere('party_id', $party)))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', LookupController::prefix($q))))
            ->orderBy('item_no')->forPage(LookupController::page(), LookupController::LIMIT)->get()
            ->map(fn (Item $i) => ['id' => $i->id, 'label' => $i->pickerLabel(), 'sub' => 'Rate '.rtrim(rtrim((string) $i->price, '0'), '.').' · '.$i->uom?->code,
                'data' => self::itemData($i)]));
    }

    /** What the page fills in when an item is picked: description, UOM, tax and price (with its currency), and the GST rates. */
    public static function itemData(Item $i): array
    {
        return ['desc' => $i->description, 'uom' => (string) $i->uom_id, 'tax' => (string) $i->tax_structure_id, 'price' => (string) (float) $i->price,
            'cur' => $i->currency?->code ?? 'INR'];
    }

    /** POs of the unit and year on the top bar, newest first; ?q= searches PO no or supplier name ("starts with"). */
    public static function search(Request $request)
    {
        $q = LookupController::term($request);

        return PurchaseOrder::query()->forWork(WorkContext::unit()?->id, WorkContext::financialYear()->id)
            ->with('party:id,name')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('po_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))))
            ->latest('id');
    }

    private function page(Request $request, PurchaseOrder $po): View
    {
        $po->loadMissing(['lines.item', 'lines.uom', 'lines.taxStructure', 'party.currency', 'currency', 'transporter', 'creator', 'updater', 'approver']);
        $partyId = (int) old('party_id', $po->party_id);
        $party = $partyId ? Party::with('currency')->find($partyId) : null;
        $lineUoms = $po->lines->pluck('uom_id')->all();
        $lineTaxes = $po->lines->pluck('tax_structure_id')->all();

        return view('purchase.orders.page', [
            'po' => $po,
            'entries' => self::search($request)->limit(50)->get(),
            'suppliers' => QuotationController::pickerOptions(Party::suppliers(), Party::class, $partyId ?: null),
            'items' => $partyId ? self::itemOptions($partyId) : collect(),
            'uoms' => Uom::forPicker()->orWhereIn('id', $lineUoms)->orderBy('code')->get(),
            'taxes' => TaxStructure::forPicker()->orWhereIn('id', $lineTaxes)->orderBy('igst_rate')->orderBy('name')->get(),
            'currencies' => Currency::orderByRaw("code = 'INR' DESC")->orderBy('code')->get(['id', 'code', 'country']),
            'transporters' => Transporter::forPicker($po->transporter_id)->orderBy('name')->get(),
            'supply' => $party ? PurchaseOrderService::supplyType($party) : ($po->supply_type ?: ''),
            'company' => Company::current(),
            'rule' => ApprovalRule::for('purchase_order'),
            'approvals' => $po->exists ? $po->approvals()->with('actor:id,name')->latest('id')->limit(10)->get() : collect(),
            'history' => ActivityLog::history($po),
            'unit' => WorkContext::unit($request->user()),
            'q' => trim((string) $request->query('q')),
        ]);
    }

    /** A supplier's purchase items (and items of no party) for the item drop-downs: all while there are at most 200. */
    public static function itemOptions(int $partyId)
    {
        $list = Item::forPurchase()->with('currency:id,code')->where(fn ($q) => $q->whereNull('party_id')->orWhere('party_id', $partyId))->orderBy('item_no')->limit(201)->get();

        return $list->count() > 200 ? collect() : $list->keyBy('id');
    }

    private function blank(): PurchaseOrder
    {
        $company = Company::current();
        $po = new PurchaseOrder(['po_date' => today(), 'currency_id' => Currency::defaultId(), 'billing_address' => $company->address, 'shipping_address' => $company->address]);
        $po->status = PurchaseOrder::DRAFT;

        return $po;
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot make purchase orders.');

        return $unit;
    }

    private function authorizeUnit(PurchaseOrder $o): void
    {
        abort_unless(in_array($o->unit_id, WorkContext::unitIds(), true), 403);
    }
}
