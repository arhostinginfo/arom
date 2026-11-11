<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Crm\QuotationController;
use App\Models\Currency;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Unit;
use App\Services\StockLedger;
use App\Support\Csv;
use App\Support\ListQuery;
use App\Support\Money;
use App\Support\ReportPdf;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/*
| Purchase reports (PURCHASE tab REPORTS column): PO summary report, GRN report, Credit/Debit report (supplier), Stock
| transfer report and Stock ledger. "Stock report and Minimum stock report" is the same report as in Sales
| (reports.sales.stock, opened with ?in=purchase from the Purchase menu). GRN and supplier note dates are the GRN date,
| or the supplier invoice date while the GRN is pending match (no GRN date yet).
| As the Sales and CRM reports: options on the same page (A21), the file made on the server (A20) as CSV or PDF (S3),
| dates default to the current financial year, records only of the user's units (D10). "Show" lists the rows on the
| page, paged on the server (rule P1); "Download" streams a CSV 1,000 rows at a time, or a PDF of at most 2,000 rows
| (fewer for wide reports: App\Support\ReportPdf)
| (C9), at most 10 downloads a minute (C10). CSV keeps plain numbers; the screen and PDF group money the Indian way.
| Cancelled POs and transfers are left out unless Status = Cancelled is chosen.
*/
class PurchaseReportController extends Controller
{
    /** Upper bound of the PDF row cap; the cap of a report is pdfCap() (ReportPdf::maxRows() of its column count). */
    public const PDF_MAX_ROWS = ReportPdf::MAX_ROWS;

    /** Columns of each report, for its PDF cap (checked against the real headers in PurchaseReportsTest). */
    public const COLUMNS = ['po-summary' => 11, 'grn' => 17, 'credit-debit' => 12, 'stock-transfers' => 11, 'stock-ledger' => 7];

    public const REPORTS = [
        'po-summary' => ['title' => 'PO summary report', 'date' => 'PO date'],
        'grn' => ['title' => 'GRN report', 'date' => 'GRN date'],
        'credit-debit' => ['title' => 'Credit/Debit report (supplier)', 'date' => 'Note date'],
        'stock-transfers' => ['title' => 'Stock transfer report', 'date' => 'Transfer date'],
        'stock-ledger' => ['title' => 'Stock ledger', 'date' => 'Movement date'],
    ];

    /** Columns that hold money (Indian grouping on screen and in the PDF) and quantities (right aligned). */
    private const MONEY = ['Value before GST', 'GST', 'Total', 'Invoice amount', 'Rate', 'Amount', 'Taxable value'];

    private const NUMERIC = ['Items', 'Value before GST', 'GST', 'Total', 'Received %', 'Quantity', 'Qty in', 'Qty out', 'Balance',
        'Invoice amount', 'Invoice qty', 'Accepted', 'Rejected', 'Rate', 'Amount', 'Taxable value'];

    private const QTY = ['Quantity', 'Qty in', 'Qty out', 'Balance', 'Invoice qty', 'Accepted', 'Rejected'];

    /** Reports with a supplier filter, and with an item filter. */
    private const WITH_PARTY = ['po-summary', 'grn', 'credit-debit'];

    private const WITH_ITEM = ['stock-transfers', 'stock-ledger', 'grn'];

    /** Lazy-by-id column of each list report (CSV in the order rows were added, C13). */
    private const ID_COLUMN = ['po-summary' => 'purchase_orders.id', 'stock-transfers' => 'stock_transfer_lines.id', 'grn' => 'grn_lines.id', 'credit-debit' => 'grns.id'];

    /** Item options shown without JavaScript when there are at most this many items (as the party picker). */
    private const FALLBACK_OPTIONS = 200;

    public function panel(Request $request, string $report): View
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $year = WorkContext::financialYear();
        $data = [
            'report' => $report,
            'title' => self::REPORTS[$report]['title'],
            'dateLabel' => self::REPORTS[$report]['date'],
            'from' => $request->query('from', $year->starts_on->format('d/m/Y')),
            'to' => $request->query('to', $year->ends_on->format('d/m/Y')),
            'units' => $request->user()->units,
            'allUnits' => Unit::orderBy('name')->get(['id', 'name']),
            'parties' => in_array($report, self::WITH_PARTY, true) ? QuotationController::pickerOptions(Party::suppliers(), Party::class, $request->query('party')) : collect(),
            'items' => in_array($report, self::WITH_ITEM, true) ? $this->itemOptions($request->query('item')) : collect(),
            'statuses' => $report === 'po-summary' ? PurchaseOrder::STATUSES : StockTransfer::STATUSES,
            'matches' => [Grn::PENDING_MATCH, Grn::MATCHED],
            'qualities' => Grn::QUALITY,
            'types' => ['Credit', 'Debit'],
            'result' => null,
            'pdfCap' => self::pdfCap($report),
        ];
        if (! $request->has('show')) {
            return view('reports.purchase', $data);
        }

        $v = Validator::make($request->query(), $this->rules($report, false), $this->messages());
        $this->checkDates($v);
        if ($v->fails()) {
            return view('reports.purchase', $data)->withErrors($v);
        }
        $f = $this->filters($v->validated());
        if ($report === 'stock-ledger' && ! $f['item']) {
            return view('reports.purchase', $data + ['prompt' => true]);
        }

        return view('reports.purchase', array_merge($data, ['result' => $this->screen($request, $report, $f)]));
    }

    public function download(Request $request, string $report): Response
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $v = Validator::make($request->query(), $this->rules($report, true), $this->messages());
        $this->checkDates($v);
        $f = $this->filters($v->validate());

        if ($report === 'stock-ledger') {
            [$headers, $rows] = $this->ledgerDownload($f);
        } else {
            $s = $this->spec($report, $f);
            [$headers, $rows] = [$s['headers'], $s['query']->lazyById(1000, self::ID_COLUMN[$report], 'id')->map($s['map'])];
        }

        $name = Str::slug(self::REPORTS[$report]['title']).'-'.$f['from']->format('Ymd').'-'.$f['to']->format('Ymd');
        if ($f['format'] === 'csv') {
            return Csv::download($name.'.csv', $headers, $rows);
        }

        // Taken from the lazy rows, so at most cap + 1 rows are read before a too-large PDF is refused (C9, M1).
        // The cap depends on the number of columns (ReportPdf); the ledger's opening and closing rows are not counted.
        $cap = self::pdfCap($report);
        $limit = $cap + ($report === 'stock-ledger' ? 2 : 0);
        $rows = $rows->take($limit + 1)->values()->all();
        if (count($rows) > $limit) {
            return back()->withErrors(['format' => ReportPdf::tooMany($cap)])->withInput();
        }
        $rows = array_map(fn (array $row) => $this->formatRow($headers, $row, false), $rows);

        return Pdf::loadView('pdf.report', [
            'title' => self::REPORTS[$report]['title'],
            'filters' => $this->filterLabels($request, $report, $f),
            'note' => null,
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($name.'.pdf');
    }

    /** Supplier picker of the PO summary report: Supplier and Both parties (inactive ones too, they have old POs). */
    public function suppliers(Request $request): JsonResponse
    {
        $q = \App\Http\Controllers\LookupController::term($request);

        return response()->json(Party::query()->whereIn('type', ['Supplier', 'Both'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', \App\Http\Controllers\LookupController::prefix($q))
                ->orWhere('gstin', 'like', \App\Http\Controllers\LookupController::prefix(strtoupper($q)))))
            ->orderBy('name')->forPage(\App\Http\Controllers\LookupController::page(), \App\Http\Controllers\LookupController::LIMIT)->get(['id', 'name', 'gstin', 'status'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => trim(($p->gstin ?? '').($p->status !== 'Active' ? ' · '.$p->status : ''))]));
    }

    /** Item picker of the Stock transfer report and Stock ledger: every item, inactive ones too. */
    public function items(Request $request): JsonResponse
    {
        $q = \App\Http\Controllers\LookupController::term($request);

        return response()->json(Item::query()->with('uom:id,code')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', \App\Http\Controllers\LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', \App\Http\Controllers\LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', \App\Http\Controllers\LookupController::prefix($q))))
            ->orderBy('item_no')->forPage(\App\Http\Controllers\LookupController::page(), \App\Http\Controllers\LookupController::LIMIT)->get()
            ->map(fn (Item $i) => ['id' => $i->id, 'label' => $i->pickerLabel(), 'sub' => trim($i->uom?->code.($i->status !== 'Active' ? ' · '.$i->status : ''))]));
    }

    /** Rows one PDF of this report may have (C9): depends on its number of columns. */
    public static function pdfCap(string $report): int
    {
        return ReportPdf::maxRows(self::COLUMNS[$report]);
    }

    // ---- Options ----

    private function rules(string $report, bool $download): array
    {
        $unitIds = WorkContext::unitIds();

        return array_filter([
            'from' => ['required', 'date_format:d/m/Y'],
            'to' => ['required', 'date_format:d/m/Y'],
            'unit' => $report !== 'stock-transfers' ? ['nullable', 'integer', Rule::in($unitIds)] : null,
            'party' => in_array($report, self::WITH_PARTY, true) ? ['nullable', 'integer'] : null,
            'match' => $report === 'grn' ? ['nullable', Rule::in([Grn::PENDING_MATCH, Grn::MATCHED])] : null,
            'quality' => $report === 'grn' ? ['nullable', Rule::in(Grn::QUALITY)] : null,
            'type' => $report === 'credit-debit' ? ['nullable', Rule::in(['Credit', 'Debit'])] : null,
            'status' => match ($report) {
                'po-summary' => ['nullable', Rule::in(PurchaseOrder::STATUSES)],
                'stock-transfers' => ['nullable', Rule::in(StockTransfer::STATUSES)],
                default => null,
            },
            'from_unit' => $report === 'stock-transfers' ? ['nullable', 'integer', Rule::exists('units', 'id')] : null,
            'to_unit' => $report === 'stock-transfers' ? ['nullable', 'integer', Rule::exists('units', 'id')] : null,
            'item' => match ($report) {
                'stock-ledger' => $download ? ['required', 'integer', Rule::exists('items', 'id')] : ['nullable', 'integer', Rule::exists('items', 'id')],
                'stock-transfers', 'grn' => ['nullable', 'integer'],
                default => null,
            },
            'format' => $download ? ['required', Rule::in(['csv', 'pdf'])] : null,
        ]);
    }

    private function messages(): array
    {
        return ['from.date_format' => 'Enter From date as dd/mm/yyyy.', 'to.date_format' => 'Enter To date as dd/mm/yyyy.', 'item.required' => 'Choose the item whose ledger you want.'];
    }

    private function checkDates(\Illuminate\Validation\Validator $v): void
    {
        $v->after(function ($v) {
            $d = $v->getData();
            $ok = fn ($k) => is_string($d[$k] ?? null) && Carbon::canBeCreatedFromFormat($d[$k], 'd/m/Y');
            if ($ok('from') && $ok('to') && Carbon::createFromFormat('d/m/Y', $d['to'])->lt(Carbon::createFromFormat('d/m/Y', $d['from'])->startOfDay())) {
                $v->errors()->add('to', 'To date must be on or after From date.');
            }
        });
    }

    private function filters(array $f): array
    {
        return [
            'from' => Carbon::createFromFormat('d/m/Y', $f['from'])->startOfDay(),
            'to' => Carbon::createFromFormat('d/m/Y', $f['to'])->endOfDay(),
            'units' => ! empty($f['unit']) ? [(int) $f['unit']] : WorkContext::unitIds(),
            'unit' => ! empty($f['unit']) ? (int) $f['unit'] : null,
            'party' => ! empty($f['party']) ? (int) $f['party'] : null,
            'status' => $f['status'] ?? null,
            'item' => ! empty($f['item']) ? (int) $f['item'] : null,
            'match' => $f['match'] ?? null,
            'quality' => $f['quality'] ?? null,
            'type' => $f['type'] ?? null,
            'from_unit' => ! empty($f['from_unit']) ? (int) $f['from_unit'] : null,
            'to_unit' => ! empty($f['to_unit']) ? (int) $f['to_unit'] : null,
            'format' => $f['format'] ?? null,
            'raw_from' => $f['from'],
            'raw_to' => $f['to'],
        ];
    }

    private function filterLabels(Request $request, string $report, array $f): array
    {
        return array_filter([
            self::REPORTS[$report]['date'] => $f['raw_from'].' to '.$f['raw_to'],
            'Unit' => $report !== 'stock-transfers' ? $request->user()->units->whereIn('id', $f['units'])->pluck('name')->implode(', ') : null,
            'Supplier' => $f['party'] ? Party::withTrashed()->find($f['party'])?->name : null,
            'From' => $f['from_unit'] ? Unit::find($f['from_unit'])?->name : null,
            'To' => $f['to_unit'] ? Unit::find($f['to_unit'])?->name : null,
            'Item' => $f['item'] ? Item::withTrashed()->find($f['item'])?->pickerLabel() : null,
            'Match' => $f['match'],
            'Quality' => $f['quality'],
            'Type' => $f['type'],
            'Status' => $f['status'] ?? (in_array($report, ['po-summary', 'stock-transfers'], true) ? 'All but Cancelled' : null),
        ]);
    }

    /** The item select without JavaScript: every item while there are few, otherwise only the chosen one (the picker searches). */
    private function itemOptions($selected): Collection
    {
        $list = Item::query()->orderBy('item_no')->limit(self::FALLBACK_OPTIONS + 1)->get(['id', 'item_no', 'description', 'internal_item_no']);
        $list = $list->count() > self::FALLBACK_OPTIONS ? collect() : $list->mapWithKeys(fn (Item $i) => [$i->id => $i->pickerLabel()]);
        if ($selected && ! $list->has($selected) && ($i = Item::withTrashed()->find($selected))) {
            $list->put($i->id, $i->pickerLabel());
        }

        return $list;
    }

    // ---- Reports ----

    /** PO summary: one row per purchase order (Cancelled left out unless chosen), with items, values, GST and received %. */
    private function poSpec(array $f): Collection
    {
        $base = fn () => PurchaseOrder::query()->whereIn('purchase_orders.unit_id', $f['units'])
            ->whereBetween('purchase_orders.po_date', [$f['from']->toDateString(), $f['to']->toDateTimeString()])
            ->when($f['party'], fn ($w) => $w->where('purchase_orders.party_id', $f['party']))
            ->when($f['status'], fn ($w) => $w->where('purchase_orders.status', $f['status']), fn ($w) => $w->where('purchase_orders.status', '!=', PurchaseOrder::CANCELLED));

        return collect([
            'headers' => ['PO no', 'PO date', 'Supplier', 'Items', 'Value before GST', 'GST', 'Total', 'Currency', 'Received %', 'Status', 'Unit'],
            'query' => $base()->withCount('lines')->withSum('lines as ordered_qty', 'quantity')->withSum('lines as received_qty', 'received_quantity')
                ->with(['party:id,name', 'currency:id,code', 'unit:id,name']),
            'order' => fn ($q) => $q->orderBy('purchase_orders.po_date')->orderBy('purchase_orders.id'),
            'map' => fn (PurchaseOrder $po) => [
                $po->po_no, Csv::date($po->po_date), $po->party?->name, $po->lines_count, $po->taxable_value,
                number_format((float) $po->cgst_amount + (float) $po->sgst_amount + (float) $po->igst_amount, 2, '.', ''), $po->grand_total,
                $po->currencyCode(), number_format((float) $po->ordered_qty > 0 ? (float) $po->received_qty / (float) $po->ordered_qty * 100 : 0, 1, '.', ''),
                $po->status, $po->unit?->name,
            ],
            'link' => request()->user()->canAny(['purchase-order.view', 'purchase-order-approval.view']) ? fn (PurchaseOrder $po) => route('purchase.orders.show', $po) : null,
            // One totals row per currency: an import PO is in the supplier's currency and is not added to rupees.
            'totals' => fn () => $base()->groupBy('purchase_orders.currency_id')->orderBy('purchase_orders.currency_id')
                ->selectRaw('purchase_orders.currency_id, COUNT(*) AS n, SUM(taxable_value) AS tv, SUM(cgst_amount + sgst_amount + igst_amount) AS gst, SUM(grand_total) AS gt')
                ->get()->map(fn ($t) => ['Total · '.Money::indian($t->n).' PO'.($t->n == 1 ? '' : 's'), '', '', '', Money::indian($t->tv, 2), Money::indian($t->gst, 2), Money::indian($t->gt, 2),
                    self::currencyCode($t->currency_id), '', '', ''])->all(),
        ]);
    }

    /** Stock transfer report: one row per transfer line, transfers from or to the user's units. */
    private function transferSpec(array $f): Collection
    {
        $mine = WorkContext::unitIds();
        $dt = config('nursery.datetime_format');

        return collect([
            'headers' => ['ST no', 'Date', 'From', 'To', 'Item no', 'Item description', 'Quantity', 'UOM', 'Status', 'Approved by', 'Approved on'],
            'query' => StockTransferLine::query()->join('stock_transfers as t', 't.id', '=', 'stock_transfer_lines.stock_transfer_id')
                ->whereNull('t.deleted_at')
                ->where(fn ($w) => $w->whereIn('t.from_unit_id', $mine)->orWhereIn('t.to_unit_id', $mine))
                ->whereBetween('t.transfer_date', [$f['from']->toDateString(), $f['to']->toDateTimeString()])
                ->when($f['from_unit'], fn ($w) => $w->where('t.from_unit_id', $f['from_unit']))
                ->when($f['to_unit'], fn ($w) => $w->where('t.to_unit_id', $f['to_unit']))
                ->when($f['item'], fn ($w) => $w->where('stock_transfer_lines.item_id', $f['item']))
                ->when($f['status'], fn ($w) => $w->where('t.status', $f['status']), fn ($w) => $w->where('t.status', '!=', StockTransfer::CANCELLED))
                ->select('stock_transfer_lines.*')
                ->with(['transfer.fromUnit:id,name', 'transfer.toUnit:id,name', 'transfer.approver:id,name', 'item' => self::itemColumns(), 'uom:id,code']),
            'order' => fn ($q) => $q->orderBy('t.transfer_date')->orderBy('stock_transfer_lines.id'),
            'map' => fn (StockTransferLine $l) => [
                $l->transfer->transfer_no, Csv::date($l->transfer->transfer_date), $l->transfer->fromUnit?->name, $l->transfer->toUnit?->name,
                $l->item?->item_no, $l->item?->description, $l->quantity, $l->uom?->code, $l->transfer->status,
                $l->transfer->approver?->name, $l->transfer->approved_at?->format($dt) ?? '',
            ],
            'link' => request()->user()->canAny(['stock-transfer.view', 'stock-transfer-approval.view']) ? fn (StockTransferLine $l) => route('purchase.stock-transfers.show', $l->stock_transfer_id) : null,
            'totals' => null,
        ]);
    }

    private function spec(string $report, array $f): Collection
    {
        return match ($report) {
            'po-summary' => $this->poSpec($f),
            'stock-transfers' => $this->transferSpec($f),
            'grn' => $this->grnSpec($f),
            'credit-debit' => $this->noteSpec($f),
        };
    }

    private static function currencyCode($id): string
    {
        static $codes = [];

        return $id ? ($codes[(int) $id] ??= Currency::find($id)?->code ?? 'INR') : 'INR';
    }

    /**
     * GRNs of the user's units, not cancelled, of the given invoice types, dated in the period. The date is the GRN date,
     * or the supplier invoice date while the GRN is pending match (it has no GRN date until it matches).
     */
    private function grnBase(array $f, array $types, string $p = 'grns.'): \Closure
    {
        return fn ($q) => $q->whereIn($p.'unit_id', $f['units'])->whereNull($p.'deleted_at')->where($p.'status', '!=', Grn::CANCELLED)
            ->whereIn($p.'invoice_type', $types)
            ->whereRaw('COALESCE('.$p.'grn_date, '.$p.'invoice_date) BETWEEN ? AND ?', [$f['from']->toDateString(), $f['to']->toDateTimeString()])
            ->when($f['party'], fn ($w) => $w->where($p.'party_id', $f['party']));
    }

    /** GRN report: one row per line of the purchase GRNs, with invoice, PO, quantities and the quality decision. */
    private function grnSpec(array $f): Collection
    {
        $base = fn () => GrnLine::query()->join('grns as g', 'g.id', '=', 'grn_lines.grn_id')
            ->tap($this->grnBase($f, ['Purchase'], 'g.'))
            ->when($f['match'], fn ($w) => $w->where('g.match_status', $f['match']))
            ->when($f['quality'], fn ($w) => $w->where('g.quality_status', $f['quality']))
            ->when($f['item'], fn ($w) => $w->where('grn_lines.item_id', $f['item']));

        return collect([
            'headers' => ['GRN no', 'GRN date', 'Supplier', 'Invoice no', 'Invoice date', 'Invoice amount', 'PO no', 'Item no', 'Item description',
                'Invoice qty', 'Accepted', 'Rejected', 'Rate', 'Amount', 'Currency', 'Quality', 'Unit'],
            'query' => $base()->select('grn_lines.*')->with(['grn.party:id,name', 'grn.currency:id,code', 'grn.unit:id,name', 'order:id,po_no', 'item' => self::itemColumns()]),
            'order' => fn ($q) => $q->orderByRaw('COALESCE(g.grn_date, g.invoice_date)')->orderBy('grn_lines.id'),
            'map' => fn (GrnLine $l) => [
                $l->grn->grn_no ?? Grn::PENDING_MATCH, Csv::date($l->grn->grn_date), $l->grn->party?->name, $l->grn->invoice_no, Csv::date($l->grn->invoice_date),
                $l->grn->invoice_amount, $l->order?->po_no, $l->item?->item_no, $l->item?->description ?? $l->description,
                $l->quantity, $l->accepted_quantity, $l->rejected_quantity, $l->rate, $l->total, $l->grn->currencyCode(), $l->grn->quality_status, $l->grn->unit?->name,
            ],
            'link' => request()->user()->canAny(['grn.view', 'grn-quality.view']) ? fn (GrnLine $l) => route('purchase.grns.show', $l->grn_id) : null,
            // Quantities and amounts of the lines listed, per currency (the invoice amount repeats on each line, so it is not added).
            'totals' => fn () => $base()->groupBy('g.currency_id')->orderBy('g.currency_id')
                ->selectRaw('g.currency_id, COUNT(DISTINCT g.id) AS n, SUM(grn_lines.quantity) AS q, SUM(grn_lines.accepted_quantity) AS a, SUM(grn_lines.rejected_quantity) AS r, SUM(grn_lines.total) AS t')
                ->get()->map(fn ($t) => ['Total · '.Money::indian($t->n).' GRN'.($t->n == 1 ? '' : 's'), '', '', '', '', '', '', '', '',
                    Money::qty($t->q), Money::qty($t->a), Money::qty($t->r), '', Money::indian($t->t, 2), self::currencyCode($t->currency_id), '', ''])->all(),
        ]);
    }

    /** Credit/Debit report (supplier): supplier credit and debit notes entered as GRNs, one row per note, totals per type. */
    private function noteSpec(array $f): Collection
    {
        $base = fn () => Grn::query()->tap($this->grnBase($f, $f['type'] ? [$f['type']] : ['Credit', 'Debit']));

        return collect([
            'headers' => ['Note no', 'Type', 'Note date', 'Supplier', 'GST no', 'Supplier ref no', 'Supplier ref date', 'Taxable value', 'GST', 'Total', 'Currency', 'Unit'],
            'query' => $base()->with(['party:id,name,gstin', 'currency:id,code', 'unit:id,name']),
            'order' => fn ($q) => $q->orderByRaw('COALESCE(grns.grn_date, grns.invoice_date)')->orderBy('grns.id'),
            'map' => fn (Grn $g) => [
                $g->grn_no ?? Grn::PENDING_MATCH, Grn::TITLES[$g->invoice_type] ?? $g->invoice_type, Csv::date($g->grn_date ?? $g->invoice_date), $g->party?->name, $g->party?->gstin,
                $g->invoice_no, Csv::date($g->invoice_date), $g->taxable_value,
                number_format((float) $g->cgst_amount + (float) $g->sgst_amount + (float) $g->igst_amount, 2, '.', ''), $g->grand_total, $g->currencyCode(), $g->unit?->name,
            ],
            'link' => request()->user()->canAny(['grn.view', 'grn-quality.view']) ? fn (Grn $g) => route('purchase.grns.show', $g) : null,
            'totals' => fn () => $base()->groupBy('grns.invoice_type', 'grns.currency_id')->orderBy('grns.invoice_type')->orderBy('grns.currency_id')
                ->selectRaw('grns.invoice_type, grns.currency_id, COUNT(*) AS n, SUM(taxable_value) AS tv, SUM(cgst_amount + sgst_amount + igst_amount) AS gst, SUM(grand_total) AS gt')
                ->get()->map(fn ($t) => ['Total · '.Money::indian($t->n).' '.($t->invoice_type === 'Credit' ? 'credit' : 'debit').' note'.($t->n == 1 ? '' : 's'),
                    Grn::TITLES[$t->invoice_type] ?? $t->invoice_type, '', '', '', '', '', Money::indian($t->tv, 2), Money::indian($t->gst, 2), Money::indian($t->gt, 2),
                    self::currencyCode($t->currency_id), ''])->all(),
        ]);
    }

    /**
     * Stock ledger of one item at the chosen unit(s): opening balance at From, every movement of every source
     * (sales invoice, credit / debit note, stock transfer in / out, GRN) and the closing balance at To.
     * Anchored on stock_balances as the Stock report is, so its closing equals that report's closing:
     * closing = on hand now − net moves after To; opening = on hand now − net moves from From onwards.
     */
    private function ledgerBalances(array $f): array
    {
        $onHand = StockLedger::onHand($f['units'], [$f['item']])[$f['item']] ?? 0.0;
        $net = fn (string $op, string $date) => (float) StockMovement::query()->whereIn('unit_id', $f['units'])->where('item_id', $f['item'])
            ->where('movement_date', $op, $date)->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) AS q')->value('q');

        return [
            'opening' => round($onHand - $net('>=', $f['from']->toDateString()), 3),
            'closing' => round($onHand - $net('>', $f['to']->toDateTimeString()), 3),
        ];
    }

    private function ledgerQuery(array $f)
    {
        return StockMovement::query()->whereIn('unit_id', $f['units'])->where('item_id', $f['item'])
            ->whereBetween('movement_date', [$f['from']->toDateString(), $f['to']->toDateTimeString()]);
    }

    private const LEDGER_HEADERS = ['Date', 'Document type', 'Document no', 'Unit', 'Qty in', 'Qty out', 'Balance'];

    /** Document type, number and link of each movement, read once per batch. */
    private function ledgerDocs(Collection $moves): array
    {
        $user = request()->user();
        $ids = fn (string $type) => $moves->where('source_type', $type)->pluck('source_id')->unique()->all();
        $inv = SalesInvoice::query()->whereIn('id', $ids('sales_invoice'))->get(['id', 'invoice_no', 'invoice_type'])->keyBy('id');
        $st = StockTransfer::withTrashed()->whereIn('id', $ids(StockTransfer::LEDGER_SOURCE))->get(['id', 'transfer_no'])->keyBy('id');
        $grn = Grn::withTrashed()->whereIn('id', $ids(Grn::LEDGER_SOURCE))->get(['id', 'grn_no', 'invoice_no'])->keyBy('id');
        $canInv = $user->can('tax-invoice.view');
        $canSt = $user->canAny(['stock-transfer.view', 'stock-transfer-approval.view']);
        $canGrn = $user->canAny(['grn.view', 'grn-quality.view']);
        $units = Unit::pluck('name', 'id');

        $out = [];
        foreach ($moves as $m) {
            [$type, $no, $url] = match ($m->source_type) {
                'sales_invoice' => [['Sales' => 'Sales invoice', 'Credit' => 'Credit note', 'Debit' => 'Debit note'][$inv[$m->source_id]->invoice_type ?? 'Sales'] ?? 'Sales invoice',
                    $inv[$m->source_id]->invoice_no ?? '#'.$m->source_id, $canInv && isset($inv[$m->source_id]) ? route('sales.invoices.show', $m->source_id) : null],
                StockTransfer::LEDGER_SOURCE => [(float) $m->quantity_in > 0 ? 'Stock transfer in' : 'Stock transfer out',
                    $st[$m->source_id]->transfer_no ?? '#'.$m->source_id, $canSt && isset($st[$m->source_id]) ? route('purchase.stock-transfers.show', $m->source_id) : null],
                Grn::LEDGER_SOURCE => ['GRN', $grn[$m->source_id]->grn_no ?? $grn[$m->source_id]->invoice_no ?? '#'.$m->source_id,
                    $canGrn && isset($grn[$m->source_id]) ? route('purchase.grns.show', $m->source_id) : null],
                default => [Str::ucfirst(str_replace('_', ' ', $m->source_type)), '#'.$m->source_id, null],
            };
            $out[$m->id] = ['type' => $type, 'no' => $no, 'url' => $url, 'unit' => $units[$m->unit_id] ?? ''];
        }

        return $out;
    }

    private function ledgerRow(StockMovement $m, array $doc, float $balance): array
    {
        $fmt = fn ($q) => number_format((float) $q, 3, '.', '');

        return [Csv::date($m->movement_date), $doc['type'], $doc['no'], $doc['unit'], $fmt($m->quantity_in), $fmt($m->quantity_out), $fmt($balance)];
    }

    /** CSV / PDF rows: opening, the movements in date order (read 1,000 at a time by date and id, keyset), closing. */
    private function ledgerDownload(array $f): array
    {
        ['opening' => $opening, 'closing' => $closing] = $this->ledgerBalances($f);
        $fmt = fn ($q) => number_format((float) $q, 3, '.', '');
        $rows = LazyCollection::make(function () use ($f, $opening, $closing, $fmt) {
            yield [Csv::date($f['from']), 'Opening balance', '', '', '', '', $fmt($opening)];
            $balance = $opening;
            $last = null;
            do {
                $batch = $this->ledgerQuery($f)
                    ->when($last, fn ($q) => $q->where(fn ($w) => $w->where('movement_date', '>', $last[0])
                        ->orWhere(fn ($s) => $s->where('movement_date', $last[0])->where('id', '>', $last[1]))))
                    ->orderBy('movement_date')->orderBy('id')->limit(1000)->get();
                $docs = $this->ledgerDocs($batch);
                foreach ($batch as $m) {
                    $balance = round($balance + (float) $m->quantity_in - (float) $m->quantity_out, 3);
                    yield $this->ledgerRow($m, $docs[$m->id], $balance);
                    $last = [$m->getRawOriginal('movement_date'), $m->id];
                }
            } while ($batch->count() === 1000);
            yield [Csv::date($f['to']), 'Closing balance', '', '', '', '', $fmt($closing)];
        });

        return [self::LEDGER_HEADERS, $rows];
    }

    // ---- On screen ----

    /** The page of rows shown on screen, formatted, with each row's link (rule P1: paged on the server). */
    private function screen(Request $request, string $report, array $f): array
    {
        $perPage = ListQuery::perPage($request);
        if ($report === 'stock-ledger') {
            ['opening' => $opening, 'closing' => $closing] = $this->ledgerBalances($f);
            $page = $this->ledgerQuery($f)->orderBy('movement_date')->orderBy('id')->paginate($perPage)->withQueryString();
            $first = $page->getCollection()->first();
            // Balance before this page: opening + the period's movements that come before its first row.
            $balance = $opening;
            if ($first) {
                $balance += (float) $this->ledgerQuery($f)->where(fn ($w) => $w->where('movement_date', '<', $first->getRawOriginal('movement_date'))
                    ->orWhere(fn ($s) => $s->where('movement_date', $first->getRawOriginal('movement_date'))->where('id', '<', $first->id)))
                    ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) AS q')->value('q');
            }
            $docs = $this->ledgerDocs($page->getCollection());
            $rows = [];
            foreach ($page->getCollection() as $m) {
                $balance = round($balance + (float) $m->quantity_in - (float) $m->quantity_out, 3);
                $rows[] = ['cells' => $this->formatRow(self::LEDGER_HEADERS, $this->ledgerRow($m, $docs[$m->id], $balance), true), 'link' => $docs[$m->id]['url'], 'linkCol' => 2];
            }
            $item = Item::withTrashed()->with('uom:id,code')->find($f['item']);

            return ['headers' => self::LEDGER_HEADERS, 'rows' => $rows, 'page' => $page, 'totals' => null,
                'opening' => Money::qty($opening), 'closing' => Money::qty($closing), 'item' => $item,
                'fromLabel' => $f['raw_from'], 'toLabel' => $f['raw_to'], 'numeric' => $this->numericCols(self::LEDGER_HEADERS)];
        }

        $s = $this->spec($report, $f);
        $page = $s['order']($s['query'])->paginate($perPage)->withQueryString();
        $rows = $page->getCollection()->map(fn ($m) => ['cells' => $this->formatRow($s['headers'], $s['map']($m), true), 'link' => $s['link'] ? $s['link']($m) : null, 'linkCol' => 0])->all();

        return ['headers' => $s['headers'], 'rows' => $rows, 'page' => $page, 'totals' => $s['totals'] ? $s['totals']() : null, 'numeric' => $this->numericCols($s['headers'])];
    }

    /** A rate with 2 to 4 decimals, as entered (GRN rates keep 4): 10.50, 10.125, 10.1234. */
    private static function rate($value): string
    {
        $s = Money::indian($value, 4);

        return preg_replace('/(\.\d\d\d*?)0+$/', '$1', $s);
    }

    /** Items of a line, deleted ones too, so an old document still shows its item. */
    private static function itemColumns(): \Closure
    {
        return fn ($q) => $q->withTrashed()->select('id', 'item_no', 'description');
    }

    private function numericCols(array $headers): array
    {
        return array_keys(array_intersect($headers, self::NUMERIC));
    }

    /** Money in Indian grouping (1,06,660.20); on screen quantities too (1,200 or 12.5). CSV keeps plain numbers. */
    private function formatRow(array $headers, array $row, bool $screen): array
    {
        foreach ($headers as $i => $h) {
            if (! isset($row[$i]) || $row[$i] === '' || $row[$i] === null) {
                continue;
            }
            if ($h === 'Rate') {
                $row[$i] = self::rate($row[$i]);
            } elseif (in_array($h, self::MONEY, true)) {
                $row[$i] = Money::indian($row[$i], 2);
            } elseif ($screen && in_array($h, self::QTY, true)) {
                $row[$i] = Money::qty($row[$i]);
            }
        }

        return $row;
    }
}
