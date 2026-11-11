<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\ImportRequest;
use App\Http\Requests\Sales\SalesOrderActionRequest;
use App\Http\Requests\Sales\SalesOrderRequest;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\SalesOrderImport;
use App\Services\SalesOrderService;
use App\Support\Csv;
use App\Support\ListQuery;
use App\Support\SalesPage;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| Sales › Sales order (SALES tab): one entry as a full form on the Sales page (with the tax invoice, A24), its
| approval (D3), the uploaded customer PO, a list paged on the server (P1), Import and Download CSV (no PDF,
| OTHER POINTS row 6). Orders are for the unit and financial year on the top bar.
*/
class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $orders)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('sales.orders.show', (int) $request->query('id'));
        }

        return $this->page($request, SalesPage::orderSearch($request)->first() ?? $this->blank());
    }

    public function create(Request $request): View
    {
        return $this->page($request, $this->blank());
    }

    public function show(Request $request, SalesOrder $order): View
    {
        $this->authorizeUnit($order);

        return $this->page($request, $order);
    }

    public function store(SalesOrderRequest $request): RedirectResponse
    {
        $so = self::inBag(fn () => $this->orders->create($request->validated(), $request->user(), $this->unit(), WorkContext::financialYear()));

        return redirect()->route('sales.orders.show', $so)->with('toast', 'Sales order '.$so->so_no.' added as a Draft');
    }

    public function update(SalesOrderRequest $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        self::inBag(fn () => $this->orders->update($order, $request->validated(), $request->user()));

        return redirect()->route('sales.orders.show', $order)->with('toast', 'Sales order '.$order->so_no.' saved');
    }

    public function destroy(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        self::inBag(fn () => $this->orders->delete($order, $request->user()));

        return redirect()->route('sales.orders.index')->with('toast', 'Sales order '.$order->so_no.' deleted');
    }

    public function submit(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        $so = self::inBag(fn () => $this->orders->submit($order, $request->user()));

        return redirect()->route('sales.orders.show', $order)->with('toast', $so->so_no.' is '.$so->status);
    }

    public function approve(SalesOrderActionRequest $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        self::inBag(fn () => $this->orders->approve($order, $request->user(), $request->validated('remarks')));

        return redirect()->route('sales.orders.show', $order)->with('toast', $order->so_no.' approved');
    }

    public function reject(SalesOrderActionRequest $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeUnit($order);
        self::inBag(fn () => $this->orders->reject($order, $request->user(), (string) $request->validated('remarks')));

        return redirect()->route('sales.orders.show', $order)->with('toast', $order->so_no.' rejected');
    }

    /** Sales order list: all, waiting for approval, approved with PO balance. Paged on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'pending' => 'Waiting for approval', 'open' => 'Approved, balance to bill', 'draft' => 'Draft or rejected'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = SalesPage::orderSearch($request)->reorder();
        match ($view) {
            'pending' => $query->where('status', SalesOrder::PENDING),
            'open' => $query->where('status', SalesOrder::APPROVED)->whereHas('lines', fn ($l) => $l->whereColumn('invoiced_quantity', '<', 'quantity')),
            'draft' => $query->whereIn('status', [SalesOrder::DRAFT, SalesOrder::REJECTED]),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'start_date', 'no' => 'so_no', 'total' => 'total_value'], 'desc');

        return view('sales.orders-list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => LookupController::term($request), 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json(SalesPage::orderSearch($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (SalesOrder $o) => [
            'id' => $o->id,
            'label' => $o->so_no.' · '.($o->party?->name ?? ''),
            'sub' => 'PO '.$o->po_number.' · '.Csv::date($o->start_date).' · '.$o->status,
            'url' => route('sales.orders.show', $o),
        ]));
    }

    /** The parts of an approved order with their PO balance, for the tax invoice (A21, D6). */
    public function poLines(SalesOrder $order): JsonResponse
    {
        $this->authorizeUnit($order);
        abort_unless($order->status === SalesOrder::APPROVED, 404);

        return response()->json($order->lines()->with(['item.uom', 'item.taxStructure', 'item.currency'])->get()->map(fn ($l) => [
            'sales_order_line_id' => $l->id,
            'item_id' => $l->item_id,
            'label' => $l->item?->pickerLabel(),
            'uom' => $l->item?->uom?->code,
            'ordered' => (float) $l->quantity,
            'invoiced' => (float) $l->invoiced_quantity,
            'balance' => $l->balance(),
            'credited' => (float) $l->credited_quantity,
            'credit_room' => $l->creditRoom(),
            'rate' => (float) $l->rate,
            'data' => $l->item ? \App\Http\Controllers\Sales\SalesInvoiceController::itemData($l->item) : null,
        ]));
    }

    /** Download CSV: one row per item of the year's orders, in the import layout, 1,000 orders at a time (C13). */
    public function export(): StreamedResponse
    {
        $headers = array_merge(['SO no', 'Status'], array_values(SalesOrderImport::COLUMNS), ['Amount', 'Invoiced']);
        $rows = SalesOrder::query()->where('unit_id', WorkContext::unit()?->id)->where('financial_year_id', WorkContext::financialYear()->id)
            ->with(['party:id,name', 'lines.item:id,item_no'])->lazyById(1000)
            ->flatMap(fn (SalesOrder $o) => $o->lines->map(fn ($l) => [
                $o->so_no, $o->status, $o->so_no, $o->party?->name, $o->po_number, Csv::date($o->start_date), Csv::date($o->end_date), $o->revision_no,
                Csv::date($o->revision_date), $l->item?->item_no, $l->quantity, $l->rate, Csv::date($l->delivery_date), $l->amount, $l->invoiced_quantity,
            ]));

        return Csv::download('sales-orders-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    public function template(): StreamedResponse
    {
        $d = today()->format('d/m/Y');

        return Csv::download('sales-order-import-template.csv', array_values(SalesOrderImport::COLUMNS), [
            ['1', 'Apex Auto Components', '4500123', $d, today()->addYear()->format('d/m/Y'), '0', '', 'BRK-2210', '1200', '42.50', today()->addMonth()->format('d/m/Y')],
            ['1', 'Apex Auto Components', '4500123', $d, '', '', '', 'BRK-2214', '600', '', today()->addMonth()->format('d/m/Y')],
        ]);
    }

    public function import(ImportRequest $request, SalesOrderImport $import): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Group', 'Party name', 'PO number', 'Start date', 'Item no', 'Quantity', 'Delivery date']);
        $count = $import->run($rows, $request->user(), $this->unit(), WorkContext::financialYear());

        return redirect()->route('sales.orders.index')->with('toast', $count.' sales order(s) imported as Drafts');
    }

    private function page(Request $request, SalesOrder $order): View
    {
        $invoice = $request->filled('inv') ? SalesInvoice::where('unit_id', WorkContext::unit()?->id)->find((int) $request->query('inv')) : null;

        return SalesPage::render($request, $invoice ?? SalesInvoiceController::blankInvoice(), $order, 'order');
    }

    /** Errors of the order's rules show on the Sales order card (the "order" error bag), not the invoice card. */
    private static function inBag(callable $fn)
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->errorBag('order');
        }
    }

    private function blank(): SalesOrder
    {
        $so = new SalesOrder(['start_date' => today(), 'revision_no' => '0']);
        $so->status = SalesOrder::DRAFT;

        return $so;
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot make sales orders.');

        return $unit;
    }

    private function authorizeUnit(SalesOrder $o): void
    {
        abort_unless(in_array($o->unit_id, WorkContext::unitIds(), true), 403);
    }
}
