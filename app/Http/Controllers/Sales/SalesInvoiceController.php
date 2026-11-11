<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Sales\InvoiceCancelRequest;
use App\Http\Requests\Sales\SalesInvoiceRequest;
use App\Models\Company;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\SalesInvoiceService;
use App\Support\Csv;
use App\Support\Gst;
use App\Support\ListQuery;
use App\Support\SalesPage;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| Sales › Tax invoice (SALES tab): tax invoices, credit notes and debit notes, one entry as a full form on the Sales
| page (A24), Generate PDF and Download CSV (no import, OTHER POINTS row 7), a list paged on the server (P1).
| Invoices are for the unit and financial year on the top bar; they are cancelled, never deleted (D16, C12).
*/
class SalesInvoiceController extends Controller
{
    public function __construct(private readonly SalesInvoiceService $invoices)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('sales.invoices.show', (int) $request->query('id'));
        }

        return $this->page($request, SalesPage::invoiceSearch($request)->first() ?? self::blankInvoice());
    }

    public function create(Request $request): View
    {
        $inv = self::blankInvoice();
        // "Make invoice" from a sales order: its party and PO are filled in.
        if ($request->filled('order') && ($so = SalesOrder::where('unit_id', WorkContext::unit()?->id)->where('status', SalesOrder::APPROVED)->find((int) $request->query('order')))) {
            $inv->party_id = $so->party_id;
            $inv->sales_order_id = $so->id;
            $inv->shipping_address = $so->party?->shipping_address ?: $so->party?->billing_address;
        }

        return $this->page($request, $inv);
    }

    public function show(Request $request, SalesInvoice $invoice): View
    {
        $this->authorizeUnit($invoice);

        return $this->page($request, $invoice);
    }

    public function store(SalesInvoiceRequest $request): RedirectResponse
    {
        $inv = self::inBag(fn () => $this->invoices->create($request->payload(), $request->user(), $this->unit(), WorkContext::financialYear()));

        return redirect()->route('sales.invoices.show', $inv)->with('toast', $inv->title().' '.$inv->invoice_no.' saved');
    }

    public function update(SalesInvoiceRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeUnit($invoice);
        self::inBag(fn () => $this->invoices->update($invoice, $request->payload(), $request->user()));

        return redirect()->route('sales.invoices.show', $invoice)->with('toast', $invoice->title().' '.$invoice->invoice_no.' saved');
    }

    public function cancel(InvoiceCancelRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeUnit($invoice);
        self::inBag(fn () => $this->invoices->cancel($invoice, $request->validated('cancel_reason'), $request->user()));

        return redirect()->route('sales.invoices.show', $invoice)->with('toast', $invoice->invoice_no.' cancelled');
    }

    /**
     * Generate PDF: a standard GST tax invoice (02/11/2026). The header (company, invoice no, date, PO) and the footer repeat
     * on every page, the item table's head repeats, and the totals, HSN summary, bank details and signatory print once,
     * on the last page. "Page X of Y" is drawn on each page after layout, when the page count is known.
     */
    public function pdf(SalesInvoice $invoice): Response
    {
        $this->authorizeUnit($invoice);

        return self::invoicePdf($invoice)->download(str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    /** The rendered invoice PDF (also used by the tests and the sample PDF). */
    public static function invoicePdf(SalesInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']);
        $company = Company::current();
        // The logo only when this server can draw it (JPG; PNG / WebP need GD). Any problem: the PDF prints without it.
        try {
            $logo = $company->pdfLogo();
        } catch (\Throwable) {
            $logo = null;
        }
        $pdf = Pdf::loadView('pdf.sales-invoice', ['inv' => $invoice, 'company' => $company, 'logo' => $logo])
            ->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $metrics = $dompdf->getFontMetrics();
        $bold = $metrics->getFont('DejaVu Sans', 'bold');
        // (11/11/2026) The header prints on page 1 only (in the view) and the footer on the last page only. dompdf repeats
        // a fixed element on every page, so the footer is drawn here.
        $canvas->page_script(fn (int $page, int $pages, Canvas $c) => self::drawPdfFooter($c, $page, $pages, $invoice, $company, $font, $bold));
        // Bottom right of every page: the invoice no and "Page X of Y", so the pages after the first are identified.
        $text = $invoice->invoice_no.'  ·  Page {PAGE_NUM} of {PAGE_COUNT}';
        $canvas->page_text($w - 28 - $metrics->getTextWidth($invoice->invoice_no.'  ·  Page 99 of 99', $font, 7.5),
            $h - 22, $text, $font, 7.5, [0.42, 0.45, 0.5]);

        return $pdf;
    }

    /** The invoice PDF footer, in the 50 pt bottom margin of the last page only (A4: 595 x 842 pt). */
    public static function drawPdfFooter(Canvas $canvas, int $page, int $pages, SalesInvoice $invoice, Company $company, string $font, string $bold): void
    {
        if ($page !== $pages) {
            return;
        }
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $grey = [0.29, 0.33, 0.39];
        $address = $company->address ? ' · '.Str::limit(preg_replace('/\s*\n\s*/', ', ', $company->address), 110) : '';
        $contact = trim(($company->phone ? 'Ph '.$company->phone : '').($company->phone && $company->email ? ' · ' : '').($company->email ?? ''));
        $canvas->line(28, $h - 44, $w - 28, $h - 44, [0.61, 0.64, 0.69], 0.75);
        $canvas->text(28, $h - 40, 'This is a computer generated '.strtolower($invoice->title()).'.', $bold, 6.8, $grey);
        $canvas->text(28, $h - 31, Str::limit($company->name.$address, 105), $font, 6.8, $grey);
        if ($contact !== '') {
            $canvas->text(28, $h - 22, $contact, $font, 6.8, $grey);
        }
    }

    /** Invoice list: all, tax invoices, credit notes, debit notes, cancelled. Paged on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'Sales' => 'Tax invoices', 'Credit' => 'Credit notes', 'Debit' => 'Debit notes', 'cancelled' => 'Cancelled'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = SalesPage::invoiceSearch($request)->reorder();
        match ($view) {
            'Sales', 'Credit', 'Debit' => $query->where('invoice_type', $view)->where('status', 'Active'),
            'cancelled' => $query->where('status', 'Cancelled'),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'invoice_date', 'no' => 'invoice_no', 'total' => 'grand_total'], 'desc');

        return view('sales.invoices-list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => LookupController::term($request), 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json(SalesPage::invoiceSearch($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (SalesInvoice $i) => [
            'id' => $i->id,
            'label' => $i->invoice_no.' · '.($i->party?->name ?? ''),
            'sub' => $i->title().' · '.Csv::date($i->invoice_date).($i->isCancelled() ? ' · Cancelled' : ''),
            'url' => route('sales.invoices.show', $i),
        ]));
    }

    /** Download CSV: one row per item of the year's invoices, 1,000 invoices at a time, in the order added (C13). */
    public function export(): StreamedResponse
    {
        $headers = ['Invoice no', 'Invoice type', 'Invoice date', 'Status', 'Party name', 'GST no', 'PO no', 'SO no', 'Shipping address', 'Mode of transport',
            'Transporter', 'Vehicle no', 'Distance (km)', 'P&F charges', 'Supply', 'Item no', 'Item description', 'HSN/SAC', 'Quantity', 'Rate', 'Taxable value',
            'CGST', 'SGST', 'IGST', 'Line total', 'Invoice taxable value', 'Invoice GST', 'Grand total', 'Currency'];
        $rows = SalesInvoice::query()->where('unit_id', WorkContext::unit()?->id)->where('financial_year_id', WorkContext::financialYear()->id)
            ->with(['party:id,name,gstin', 'salesOrder:id,so_no,po_number', 'transporter:id,name', 'currency:id,code', 'lines.item:id,item_no,description'])
            ->lazyById(1000)
            ->flatMap(fn (SalesInvoice $i) => $i->lines->map(fn ($l) => [
                $i->invoice_no, $i->invoice_type, Csv::date($i->invoice_date), $i->status, $i->party?->name, $i->party?->gstin, $i->salesOrder?->po_number,
                $i->salesOrder?->so_no, $i->shipping_address, $i->mode_of_transport, $i->transporter?->name, $i->vehicle_no, $i->distance_km, $i->pf_charges,
                $i->supply_type, $l->item?->item_no, $l->item?->description, $l->hsn_code, $l->quantity, $l->rate, $l->taxable_value, $l->cgst_amount,
                $l->sgst_amount, $l->igst_amount, $l->total, $i->taxable_value, number_format((float) $i->cgst_amount + (float) $i->sgst_amount + (float) $i->igst_amount, 2, '.', ''),
                $i->grand_total, $i->currency?->code ?? 'INR',
            ]));

        return Csv::download('sales-invoices-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    /**
     * Party picker data on the invoice: its shipping address, its approved sales orders (PO no), and the supply type and
     * currency the live totals use. Customers only (code review S7).
     */
    public function partyInfo(Party $party): JsonResponse
    {
        abort_unless(in_array($party->type, ['Customer', 'Both'], true), 404);
        $party->loadMissing('currency');

        return response()->json([
            'supply' => Company::current()->gstin ? Gst::supplyType($party, Company::current()) : '',
            'currency' => $party->currency?->code ?? 'INR',
            'shipping_address' => $party->shipping_address ?: $party->billing_address,
            'orders' => SalesPage::approvedOrders($party->id)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values(),
            'items_url' => route('sales.items.lookup', ['party' => $party->id]),
        ]);
    }

    /** Customers for the party pickers (first 20 "starts with" matches). ?all=1: every party, for the Item master. */
    public function parties(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $query = $request->boolean('all') ? Party::query()->orderBy('name') : Party::customers();
        $company = Company::current();

        return response()->json($query->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))
            ->orWhere('gstin', 'like', LookupController::prefix(strtoupper($q)))))
            ->forPage(LookupController::page(), LookupController::LIMIT)->with('currency:id,code')->get(['id', 'name', 'gstin', 'type', 'currency_id'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => trim($p->type.' '.($p->gstin ?? '')),
                // For the Sales page while typing: the party's currency and the GST split it gets.
                'data' => ['cur' => $p->currency?->code ?? 'INR', 'supply' => $company->gstin ? Gst::supplyType($p, $company) : '']]));
    }

    /** A customer's sales items for the line pickers (A22), first 20 matching item no, IPN or description. */
    public function items(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $party = $request->integer('party') ?: null;

        return response()->json(Item::forSale()->with(['uom:id,code', 'taxStructure', 'currency:id,code'])
            ->where(fn ($w) => $w->whereNull('party_id')->when($party, fn ($p) => $p->orWhere('party_id', $party)))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', LookupController::prefix($q))))
            ->orderBy('item_no')->forPage(LookupController::page(), LookupController::LIMIT)->get()
            ->map(fn (Item $i) => ['id' => $i->id, 'label' => $i->pickerLabel(), 'sub' => 'Rate '.rtrim(rtrim((string) $i->price, '0'), '.').' · '.$i->uom?->code,
                'data' => self::itemData($i)]));
    }

    /** What the Sales page needs to show amounts and GST while typing: price, its currency and the GST rates. */
    public static function itemData(Item $i): array
    {
        return ['price' => (string) (float) $i->price, 'cur' => $i->currency?->code ?? 'INR', 'cgst' => (string) (float) $i->taxStructure?->cgst_rate,
            'sgst' => (string) (float) $i->taxStructure?->sgst_rate, 'igst' => (string) (float) $i->taxStructure?->igst_rate];
    }

    public static function blankInvoice(): SalesInvoice
    {
        $inv = new SalesInvoice(['invoice_type' => 'Sales', 'invoice_date' => today(), 'mode_of_transport' => 'Road', 'pf_charges' => 0]);
        $inv->status = 'Active';

        return $inv;
    }

    private function page(Request $request, SalesInvoice $invoice): View
    {
        $order = $request->filled('so') ? SalesOrder::where('unit_id', WorkContext::unit()?->id)->find((int) $request->query('so')) : null;
        if (! $order) {
            $order = SalesPage::orderSearch($request)->first() ?? new SalesOrder(['start_date' => today(), 'revision_no' => '0']);
            $order->status ??= SalesOrder::DRAFT;
        }

        return SalesPage::render($request, $invoice, $order, $request->filled('so') ? 'order' : 'invoice');
    }

    /** Errors of the invoice's rules show on the Tax invoice card (the "invoice" error bag). */
    private static function inBag(callable $fn)
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->errorBag('invoice');
        }
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot make invoices.');

        return $unit;
    }

    private function authorizeUnit(SalesInvoice $i): void
    {
        abort_unless(in_array($i->unit_id, WorkContext::unitIds(), true), 403);
    }
}
