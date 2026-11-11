<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Crm\QuotationController;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMovement;
use App\Support\Csv;
use App\Support\Money;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/*
| Sales reports (SALES tab A2:A6): Sales order report, Sales report (summary / detailed), Export sales report,
| Credit/Debit report (customer), Stock report and Minimum stock report. Clicking a report opens its download options
| on the same page (A21); the file is made on the server (A20) as CSV or PDF (S3). Filters: From / To date (default the
| current financial year), Unit, and per report Party, Status, Summary / Detailed or Below minimum only. Cancelled
| invoices are left out. CSV keeps plain numbers so Excel can add them up; the PDF groups money the Indian way.
*/
class SalesReportController extends Controller
{
    /** Upper bound of the PDF row cap; the cap of a report is ReportPdf::maxRows() of its column count. */
    public const PDF_MAX_ROWS = \App\Support\ReportPdf::MAX_ROWS;

    public const REPORTS = [
        'orders' => ['title' => 'Sales order report', 'date' => 'Start date'],
        'sales' => ['title' => 'Sales report', 'date' => 'Invoice date'],
        'export' => ['title' => 'Export sales report', 'date' => 'Invoice date'],
        'credit-debit' => ['title' => 'Credit/Debit report (customer)', 'date' => 'Note date'],
        'stock' => ['title' => 'Stock report and Minimum stock report', 'date' => 'Movement date'],
    ];

    /**
     * Shown on the Stock report panel and PDF: where the figures come from. Every stock_movements row counts, whatever
     * document wrote it (Purchase reports, 27/10/2026: GRN and stock transfer are built).
     */
    public const STOCK_NOTE = 'Stock received comes in with the Purchase module: approved GRNs add the accepted quantity and approved stock transfers move stock between units. Tax invoices take stock out and credit notes bring it back.';

    private const MONEY = ['Rate', 'Amount', 'Taxable value', 'CGST', 'SGST', 'IGST', 'GST', 'Grand total', 'Order total', 'P&F charges'];

    public function panel(Request $request, string $report): View
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $year = WorkContext::financialYear();

        return view('reports.sales', [
            'report' => $report,
            // The Stock report is listed under Purchase too; opened from there (?in=purchase) it is shown as a Purchase report.
            'group' => $report === 'stock' && $request->query('in') === 'purchase' ? 'Purchase' : 'Sales',
            'title' => self::REPORTS[$report]['title'],
            'dateLabel' => self::REPORTS[$report]['date'],
            'from' => $year->starts_on->format('d/m/Y'),
            'to' => $year->ends_on->format('d/m/Y'),
            'units' => $request->user()->units,
            'parties' => QuotationController::pickerOptions(Party::customers(), Party::class, old('party')),
        ]);
    }

    public function download(Request $request, string $report): Response
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $f = $request->validate([
            'from' => ['required', 'date_format:d/m/Y'],
            'to' => ['required', 'date_format:d/m/Y'],
            'unit' => ['nullable', 'integer', Rule::in(WorkContext::unitIds())],
            'party' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(SalesOrder::STATUSES)],
            'detail' => ['nullable', Rule::in(['summary', 'detailed'])],
            'below' => ['nullable', 'boolean'],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ], ['from.date_format' => 'Enter From date as dd/mm/yyyy.', 'to.date_format' => 'Enter To date as dd/mm/yyyy.']);
        $from = Carbon::createFromFormat('d/m/Y', $f['from'])->startOfDay();
        $to = Carbon::createFromFormat('d/m/Y', $f['to'])->endOfDay();
        if ($to->lt($from)) {
            return back()->withErrors(['to' => 'To date must be on or after From date.'])->withInput();
        }
        $units = ! empty($f['unit']) ? [(int) $f['unit']] : WorkContext::unitIds();
        $party = $f['party'] ?? null;

        [$headers, $rows] = match ($report) {
            'orders' => $this->orders($from, $to, $units, $party, $f['status'] ?? null),
            'sales' => $this->invoices($from, $to, $units, $party, ['Sales'], ($f['detail'] ?? 'summary') === 'detailed', null),
            'export' => $this->invoices($from, $to, $units, $party, ['Sales'], ($f['detail'] ?? 'summary') === 'detailed', true),
            'credit-debit' => $this->invoices($from, $to, $units, $party, ['Credit', 'Debit'], ($f['detail'] ?? 'summary') === 'detailed', null),
            'stock' => $this->stock($from, $to, $units, ! empty($f['below'])),
        };

        $name = \Illuminate\Support\Str::slug(self::REPORTS[$report]['title']).'-'.$from->format('Ymd').'-'.$to->format('Ymd');
        if ($f['format'] === 'csv') {
            return Csv::download($name.'.csv', $headers, $rows);
        }

        // Taken from the lazy rows, so at most cap + 1 rows are read before a too-large PDF is refused (architecture M1).
        // The cap depends on the number of columns (C9, ReportPdf).
        $cap = \App\Support\ReportPdf::maxRows(count($headers));
        $rows = $rows->take($cap + 1)->values()->all();
        if (count($rows) > $cap) {
            return back()->withErrors(['format' => \App\Support\ReportPdf::tooMany($cap)])->withInput();
        }
        $money = array_keys(array_intersect($headers, self::MONEY));
        $rows = array_map(function (array $row) use ($money) {
            foreach ($money as $i) {
                if ($row[$i] !== null && $row[$i] !== '') {
                    $row[$i] = Money::indian($row[$i], 2);
                }
            }

            return $row;
        }, $rows);

        return Pdf::loadView('pdf.report', [
            'title' => self::REPORTS[$report]['title'],
            'filters' => array_filter([
                self::REPORTS[$report]['date'] => $f['from'].' to '.$f['to'],
                'Unit' => $request->user()->units->whereIn('id', $units)->pluck('name')->implode(', '),
                'Party' => $party ? Party::withTrashed()->find($party)?->name : null,
                'Status' => $f['status'] ?? null,
                'Detail' => isset($f['detail']) ? ucfirst($f['detail']) : null,
                'Show' => ! empty($f['below']) ? 'Items below minimum stock only' : null,
            ]),
            'note' => $report === 'stock' ? self::STOCK_NOTE : null,
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($name.'.pdf');
    }

    /** Sales order report: one row per item, with what is invoiced and the balance. */
    private function orders(Carbon $from, Carbon $to, array $units, ?int $party, ?string $status): array
    {
        $headers = ['SO no', 'Start date', 'End date', 'Party name', 'PO number', 'Rev no', 'Status', 'Item no', 'Item description', 'Quantity', 'Rate', 'Amount',
            'Delivery date', 'Invoiced', 'Balance', 'Unit'];
        $rows = SalesOrderLine::query()->join('sales_orders as o', 'o.id', '=', 'sales_order_lines.sales_order_id')
            ->whereNull('o.deleted_at')->whereIn('o.unit_id', $units)->whereBetween('o.start_date', [$from->toDateString(), $to->toDateTimeString()])
            ->when($party, fn ($w) => $w->where('o.party_id', $party))
            ->when($status, fn ($w) => $w->where('o.status', $status))
            ->select('sales_order_lines.*')->with(['order.party:id,name', 'order.unit:id,name', 'item:id,item_no,description'])
            ->lazyById(1000, 'sales_order_lines.id', 'id')
            ->map(fn (SalesOrderLine $l) => [
                $l->order->so_no, Csv::date($l->order->start_date), Csv::date($l->order->end_date), $l->order->party?->name, $l->order->po_number,
                $l->order->revision_no, $l->order->status, $l->item?->item_no, $l->item?->description, $l->quantity, $l->rate, $l->amount,
                Csv::date($l->delivery_date), $l->invoiced_quantity, number_format($l->balance(), 3, '.', ''), $l->order->unit?->name,
            ]);

        return [$headers, $rows];
    }

    /**
     * Invoices: $types Sales for the sales and export reports, Credit and Debit for the credit/debit report.
     * $export: true = export customers only (foreign currency, D15), false = domestic only, null = both.
     */
    private function invoices(Carbon $from, Carbon $to, array $units, ?int $party, array $types, bool $detailed, ?bool $export): array
    {
        $base = fn ($q, string $p = '') => $q->whereIn($p.'unit_id', $units)->whereIn($p.'invoice_type', $types)->where($p.'status', 'Active')
            ->whereBetween($p.'invoice_date', [$from->toDateString(), $to->toDateTimeString()])
            ->when($party, fn ($w) => $w->where($p.'party_id', $party))
            ->when($export === true, fn ($w) => $w->where($p.'supply_type', 'Export'))
            ->when($export === false, fn ($w) => $w->where($p.'supply_type', '!=', 'Export'));

        if (! $detailed) {
            $headers = ['Invoice no', 'Type', 'Invoice date', 'Party name', 'GST no', 'PO no', 'Supply', 'Currency', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Grand total', 'Unit'];
            $rows = $base(SalesInvoice::query())->with(['party:id,name,gstin', 'salesOrder:id,po_number', 'currency:id,code', 'unit:id,name'])
                ->lazyById(1000)->map(fn (SalesInvoice $i) => [
                    $i->invoice_no, $i->title(), Csv::date($i->invoice_date), $i->party?->name, $i->party?->gstin, $i->salesOrder?->po_number, $i->supply_type,
                    $i->currency?->code ?? 'INR', $i->taxable_value, $i->cgst_amount, $i->sgst_amount, $i->igst_amount, $i->grand_total, $i->unit?->name,
                ]);

            return [$headers, $rows];
        }

        $headers = ['Invoice no', 'Type', 'Invoice date', 'Party name', 'GST no', 'PO no', 'Item no', 'Item description', 'HSN/SAC', 'Quantity', 'Rate',
            'Taxable value', 'CGST', 'SGST', 'IGST', 'Amount', 'Currency', 'Unit'];
        $rows = SalesInvoiceLine::query()->join('sales_invoices as i', 'i.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->tap(fn ($q) => $base($q, 'i.'))
            ->select('sales_invoice_lines.*')->with(['invoice.party:id,name,gstin', 'invoice.salesOrder:id,po_number', 'invoice.currency:id,code', 'invoice.unit:id,name', 'item:id,item_no,description'])
            ->lazyById(1000, 'sales_invoice_lines.id', 'id')
            ->map(fn (SalesInvoiceLine $l) => [
                $l->invoice->invoice_no, $l->invoice->title(), Csv::date($l->invoice->invoice_date), $l->invoice->party?->name, $l->invoice->party?->gstin,
                $l->invoice->salesOrder?->po_number, $l->item?->item_no, $l->item?->description, $l->hsn_code, $l->quantity, $l->rate, $l->taxable_value,
                $l->cgst_amount, $l->sgst_amount, $l->igst_amount, $l->total, $l->invoice->currency?->code ?? 'INR', $l->invoice->unit?->name,
            ]);

        return [$headers, $rows];
    }

    /**
     * Stock report: per item of the unit(s), opening stock before From, stock in and out in the period, closing stock
     * at To and whether it is below the item's minimum. Items are read 1,000 at a time; for each batch, stock on hand
     * comes from stock_balances (one row per unit and item) and only that batch's movements from From onwards are read
     * from the ledger, through the (unit, item, date) index (architecture M2, database SL-M3):
     * closing at To = on hand now − net moves after To; opening = closing − in + out within the period.
     */
    private function stock(Carbon $from, Carbon $to, array $units, bool $belowOnly): array
    {
        $headers = ['Item no', 'Item description', 'UOM', 'Min stock', 'Max stock', 'Opening', 'In', 'Out', 'Closing', 'Below minimum'];
        $f = $from->toDateString();
        // End of the To day, so a movement on To counts as in the period also where a date is kept with a time (SQLite).
        $t = $to->toDateTimeString();
        $fmt = fn ($q) => number_format((float) $q, 3, '.', '');
        $rows = Item::query()->where('items.status', 'Active')
            ->when($belowOnly, fn ($w) => $w->whereNotNull('items.min_stock'))
            ->select('items.id', 'items.item_no', 'items.description', 'items.uom_id', 'items.min_stock', 'items.max_stock')
            ->with('uom:id,code')
            ->lazyById(1000, 'items.id', 'id')
            ->chunk(1000)
            ->flatMap(function ($batch) use ($units, $f, $t, $fmt, $belowOnly) {
                $ids = $batch->pluck('id')->all();
                $onHand = \App\Services\StockLedger::onHand($units, $ids);
                $moves = StockMovement::query()->whereIn('unit_id', $units)->whereIn('item_id', $ids)->where('movement_date', '>=', $f)
                    ->groupBy('item_id')->select('item_id')
                    ->selectRaw('SUM(CASE WHEN movement_date <= ? THEN quantity_in ELSE 0 END) AS qty_in', [$t])
                    ->selectRaw('SUM(CASE WHEN movement_date <= ? THEN quantity_out ELSE 0 END) AS qty_out', [$t])
                    ->selectRaw('SUM(CASE WHEN movement_date > ? THEN quantity_in - quantity_out ELSE 0 END) AS after_to', [$t])
                    ->get()->keyBy('item_id');

                return $batch->map(function ($i) use ($onHand, $moves, $fmt) {
                    $m = $moves->get($i->id);
                    $in = (float) ($m->qty_in ?? 0);
                    $out = (float) ($m->qty_out ?? 0);
                    $closing = round(($onHand[$i->id] ?? 0) - (float) ($m->after_to ?? 0), 3);
                    $opening = round($closing - $in + $out, 3);
                    $below = $i->min_stock !== null && $closing < (float) $i->min_stock;

                    return ['below' => $below, 'row' => [
                        $i->item_no, $i->description, $i->uom?->code, $i->min_stock, $i->max_stock, $fmt($opening), $fmt($in), $fmt($out), $fmt($closing), $below ? 'Yes' : 'No',
                    ]];
                })->filter(fn ($r) => ! $belowOnly || $r['below'])->pluck('row');
            });

        return [$headers, $rows];
    }
}
