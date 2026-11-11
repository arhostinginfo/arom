<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Purchase reports (27/10/2026): PO summary, GRN, Credit/Debit (supplier), Stock report (shared with Sales),
 * Stock transfer and Stock ledger: on screen, CSV, PDF, filters, rights, unit scoping, running balance, menu links.
 * Test database only; documents are written straight to the tables so the reports are tested on their own.
 */
class PurchaseReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private array $d;

    protected function setUp(): void
    {
        parent::setUp();
        [$u1, $u2] = Unit::orderBy('id')->get()->all();
        $fy = FinancialYear::forDate(today());
        $inr = Currency::defaultId();
        $tax = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '0602', 'type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $tax->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mkItem = fn ($no, $min) => Item::create(['description' => 'Plant '.$no, 'item_no' => $no, 'type' => 'Both', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => 10, 'min_stock' => $min, 'status' => 'Active']);
        $s1 = Party::create(['name' => 'Green Leaf Growers', 'gstin' => '27AAAPG1234K1Z5', 'type' => 'Supplier', 'status' => 'Active', 'currency_id' => $inr]);
        $s2 = Party::create(['name' => 'Kaveri Seeds', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Supplier', 'status' => 'Active', 'currency_id' => $inr]);
        $a = $mkItem('ARC-0001', 150);
        $b = $mkItem('FCS-0002', null);
        $admin = User::where('email', 'admin@nursery.local')->first();
        $today = today()->toDateString();

        $po = function (Unit $u, Party $p, string $no, string $status, float $tv, float $gst, array $lines) use ($fy, $today, $tax, $nos) {
            $o = new PurchaseOrder;
            $o->forceFill(['unit_id' => $u->id, 'financial_year_id' => $fy->id, 'po_no' => $no, 'po_date' => $today, 'party_id' => $p->id, 'supply_type' => 'Intra-state',
                'taxable_value' => $tv, 'cgst_amount' => $gst / 2, 'sgst_amount' => $gst / 2, 'igst_amount' => 0, 'grand_total' => $tv + $gst, 'status' => $status])->save();
            foreach ($lines as $i => [$item, $qty, $rcv]) {
                $l = new PurchaseOrderLine;
                $l->forceFill(['purchase_order_id' => $o->id, 'line_no' => $i + 1, 'item_id' => $item->id, 'quantity' => $qty, 'uom_id' => $nos->id, 'rate' => 10,
                    'taxable_value' => $qty * 10, 'tax_structure_id' => $tax->id, 'total' => $qty * 11.8, 'received_quantity' => $rcv])->save();
            }

            return $o;
        };
        $po1 = $po($u1, $s1, 'PO/26-27/0001', 'Partly received', 1234567.50, 222222.20, [[$a, 10, 5], [$b, 10, 0]]);
        $po($u2, $s2, 'PO/26-27/0002', 'Draft', 2000, 360, [[$a, 5, 0]]);
        $po($u1, $s1, 'PO/26-27/0003', 'Cancelled', 999, 0, [[$a, 1, 0]]);

        $st = function (string $no, Unit $from, Unit $to, string $status, Item $item, float $qty) use ($fy, $today, $admin, $nos) {
            $t = new StockTransfer;
            $t->forceFill(['financial_year_id' => $fy->id, 'transfer_no' => $no, 'transfer_date' => $today, 'from_unit_id' => $from->id, 'to_unit_id' => $to->id,
                'status' => $status, 'approved_by' => $status === 'Approved' ? $admin->id : null, 'approved_at' => $status === 'Approved' ? now() : null])->save();
            $l = new StockTransferLine;
            $l->forceFill(['stock_transfer_id' => $t->id, 'line_no' => 1, 'item_id' => $item->id, 'quantity' => $qty, 'uom_id' => $nos->id])->save();

            return $t;
        };
        $st1 = $st('ST/26-27/0001', $u1, $u2, 'Approved', $a, 20);
        $st('ST/26-27/0002', $u2, $u1, 'Draft', $b, 3);
        $st('ST/26-27/0003', $u2, $u1, 'Cancelled', $b, 4);

        $grn = function (array $h, array $lines = []) use ($u1, $fy, $s1, $today, $nos, $tax) {
            $g = new Grn;
            $g->forceFill($h + ['unit_id' => $u1->id, 'financial_year_id' => $fy->id, 'party_id' => $s1->id, 'invoice_date' => $today, 'supply_type' => 'Intra-state',
                'status' => 'Saved', 'quality_status' => 'Pending', 'match_status' => 'Pending match'])->save();
            foreach ($lines as $i => $l) {
                $gl = new GrnLine;
                $gl->forceFill($l + ['grn_id' => $g->id, 'line_no' => $i + 1, 'uom_id' => $nos->id, 'tax_structure_id' => $tax->id])->save();
            }

            return $g;
        };
        $g1 = $grn(['grn_no' => 'GRN/26-27/0001', 'grn_date' => $today, 'invoice_type' => 'Purchase', 'invoice_no' => 'GLG-77', 'invoice_amount' => 330.40,
            'taxable_value' => 280, 'cgst_amount' => 25.2, 'sgst_amount' => 25.2, 'grand_total' => 330.40, 'match_status' => 'Matched', 'quality_status' => 'Approved'],
            [['purchase_order_id' => $po1->id, 'item_id' => $a->id, 'quantity' => 30, 'accepted_quantity' => 28, 'rejected_quantity' => 2, 'rate' => 10, 'taxable_value' => 280, 'total' => 330.40]]);
        $grn(['invoice_type' => 'Purchase', 'invoice_no' => 'GLG-78', 'invoice_amount' => 100, 'grand_total' => 59],
            [['item_id' => $b->id, 'quantity' => 5, 'rate' => 10, 'taxable_value' => 50, 'total' => 59]]);
        $grn(['grn_no' => 'GRN/26-27/0002', 'grn_date' => $today, 'invoice_type' => 'Credit', 'invoice_no' => 'CN-5', 'invoice_amount' => 118,
            'taxable_value' => 100, 'cgst_amount' => 9, 'sgst_amount' => 9, 'grand_total' => 118, 'match_status' => 'Matched']);
        $grn(['grn_no' => 'GRN/26-27/0003', 'grn_date' => $today, 'invoice_type' => 'Debit', 'invoice_no' => 'DN-9', 'invoice_amount' => 236,
            'taxable_value' => 200, 'cgst_amount' => 18, 'sgst_amount' => 18, 'grand_total' => 236, 'match_status' => 'Matched']);
        $grn(['grn_no' => 'GRN/26-27/0004', 'grn_date' => $today, 'invoice_type' => 'Credit', 'invoice_no' => 'CN-OLD', 'invoice_amount' => 50,
            'taxable_value' => 50, 'grand_total' => 50, 'match_status' => 'Matched', 'status' => 'Cancelled']);

        // Stock of ARC-0001 at Unit 1: 100 opening 10 days ago, then today GRN +28, transfer out −20, invoice −5; 3 days ahead −1.
        DB::transaction(function () use ($u1, $u2, $a, $g1, $st1) {
            StockLedger::move($u1->id, $a->id, today()->subDays(10)->toDateString(), 100, 0, 'test_opening', 1);
            StockLedger::move($u1->id, $a->id, today()->toDateString(), 28, 0, Grn::LEDGER_SOURCE, $g1->id);
            StockLedger::move($u1->id, $a->id, today()->toDateString(), 0, 20, StockTransfer::LEDGER_SOURCE, $st1->id);
            StockLedger::move($u2->id, $a->id, today()->toDateString(), 20, 0, StockTransfer::LEDGER_SOURCE, $st1->id);
            StockLedger::move($u1->id, $a->id, today()->toDateString(), 0, 5, 'sales_invoice', 9999);
            StockLedger::move($u1->id, $a->id, today()->addDays(3)->toDateString(), 0, 1, 'sales_invoice', 9998);
        });

        $this->d = compact('u1', 'u2', 's1', 's2', 'a', 'b', 'admin', 'po1', 'st1', 'g1');
    }

    private function buyer(): User
    {
        $u = User::create(['name' => 'Rahul', 'email' => 'rahul@company.example', 'mobile' => '9822411111', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Purchase');
        $u->units()->sync([$this->d['u1']->id]);

        return $u;
    }

    private function range(array $q = []): array
    {
        return ['from' => today()->subDays(5)->format('d/m/Y'), 'to' => today()->format('d/m/Y')] + $q;
    }

    private function csv(User $u, string $report, array $q = []): string
    {
        return $this->actingAs($u)->get("/reports/purchase/$report/download?".http_build_query($this->range($q + ['format' => 'csv'])))->assertOk()->streamedContent();
    }

    private function show(User $u, string $report, array $q = [])
    {
        return $this->actingAs($u)->get("/reports/purchase/$report?".http_build_query($this->range($q + ['show' => 1])))->assertOk();
    }

    public function test_po_summary_on_screen_csv_pdf_filters_and_totals(): void
    {
        $admin = $this->d['admin'];
        $this->actingAs($admin)->get('/reports/purchase/po-summary')->assertOk()->assertSee('Report options')->assertDontSee('PO/26-27/0001');

        $page = $this->show($admin, 'po-summary');
        $page->assertSee('PO/26-27/0001')->assertSee('PO/26-27/0002')->assertDontSee('PO/26-27/0003');   // cancelled left out
        $page->assertSee(route('purchase.orders.show', $this->d['po1']), false);                            // link to the PO
        $page->assertSee('12,34,567.50')->assertSee('14,56,789.70');                                        // Indian grouping
        $page->assertSee('Total · 2 POs')->assertSee('12,36,567.50');                                        // totals row
        $page->assertSee('25.0');                                                                          // received 5 of 20

        $csv = $this->csv($admin, 'po-summary');
        $this->assertStringContainsString('"PO no","PO date",Supplier,Items,"Value before GST",GST,Total,Currency,"Received %",Status,Unit', $csv);
        $this->assertStringContainsString('PO/26-27/0001,'.today()->format('d/m/Y').',"Green Leaf Growers",2,1234567.50,222222.20,1456789.70,INR,25.0,"Partly received"', $csv);
        $this->assertStringNotContainsString('PO/26-27/0003', $csv);
        $this->assertStringContainsString('PO/26-27/0003', $this->csv($admin, 'po-summary', ['status' => 'Cancelled']));
        $bySupplier = $this->csv($admin, 'po-summary', ['party' => $this->d['s2']->id]);
        $this->assertStringContainsString('PO/26-27/0002', $bySupplier);
        $this->assertStringNotContainsString('PO/26-27/0001', $bySupplier);

        $pdf = $this->actingAs($admin)->get('/reports/purchase/po-summary/download?'.http_build_query($this->range(['format' => 'pdf'])));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        // Validation: dates, status, unit not mine.
        $this->actingAs($admin)->get('/reports/purchase/po-summary/download?from=31/12/2026&to=01/01/2026&format=csv')->assertSessionHasErrors('to');
        $this->show($admin, 'po-summary', ['status' => 'Nope'])->assertSee('Check the options marked in red');
        $this->actingAs($this->buyer())->get('/reports/purchase/po-summary/download?'.http_build_query($this->range(['format' => 'csv', 'unit' => $this->d['u2']->id])))
            ->assertSessionHasErrors('unit');
    }

    public function test_pdf_cap_scales_with_the_columns_and_each_reports_column_count_is_right(): void
    {
        // ~6,000 cells, between 200 and 2,000 rows (C9 revised: dompdf memory grows with cells).
        $this->assertSame([2000, 2000, 857, 545, 352, 240, 200], array_map([\App\Support\ReportPdf::class, 'maxRows'], [1, 3, 7, 11, 17, 25, 40]));
        // The column counts used for the cap match the real headers.
        $admin = $this->d['admin'];
        foreach (\App\Http\Controllers\Reports\PurchaseReportController::COLUMNS as $r => $n) {
            $q = $r === 'stock-ledger' ? ['item' => $this->d['a']->id] : [];
            $head = str_getcsv(strtok(ltrim($this->csv($admin, $r, $q), "\xEF\xBB\xBF"), "\n"));
            $this->assertCount($n, $head, $r);
            $this->actingAs($admin)->get("/reports/purchase/$r")->assertSee('PDF of this report is limited to '.number_format(\App\Http\Controllers\Reports\PurchaseReportController::pdfCap($r)).' rows');
        }
    }

    public function test_widest_purchase_pdf_at_its_cap_renders_in_tables_of_50_within_memory(): void
    {
        // GRN report: 17 columns, cap 352 rows. A real PDF, so the memory of the chunked template is checked.
        $cap = \App\Http\Controllers\Reports\PurchaseReportController::pdfCap('grn');
        $g = $this->d['g1'];
        $rows = [];
        for ($i = 2; $i <= $cap - 1; $i++) {   // + the 2 lines of the setUp (G1 and the pending one)
            $rows[] = ['grn_id' => $g->id, 'line_no' => $i, 'item_id' => $this->d['a']->id, 'quantity' => 1, 'rate' => 10, 'taxable_value' => 10, 'total' => 11.8, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('grn_lines')->insert($chunk);
        }
        $html = view('pdf.report', ['title' => 'T', 'filters' => [], 'note' => null, 'headers' => ['A', 'B'], 'rows' => array_fill(0, 120, ['x', 'y'])])->render();
        $this->assertSame(3, substr_count($html, '<table>'));   // 50 + 50 + 20, each with its header
        $this->assertSame(3, substr_count($html, '<thead>'));

        gc_collect_cycles();
        memory_reset_peak_usage();
        cache()->flush();
        $res = $this->actingAs($this->d['admin'])->get('/reports/purchase/grn/download?'.http_build_query($this->range(['format' => 'pdf'])));
        $res->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertLessThan(300 * 1048576, memory_get_peak_usage(), 'GRN PDF at its cap stays well under 300 MB');
        DB::table('grn_lines')->insert(['grn_id' => $g->id, 'line_no' => 999, 'item_id' => $this->d['a']->id, 'quantity' => 1, 'rate' => 10, 'taxable_value' => 10, 'total' => 11.8]);
        cache()->flush();
        $this->actingAs($this->d['admin'])->get('/reports/purchase/grn/download?'.http_build_query($this->range(['format' => 'pdf'])))
            ->assertSessionHasErrors(['format' => 'Too many rows for PDF (more than 352 for this report). Use CSV or narrow the dates.']);
    }

    public function test_grn_rates_keep_up_to_4_decimals_and_deleted_items_still_show(): void
    {
        $admin = $this->d['admin'];
        GrnLine::where('grn_id', $this->d['g1']->id)->update(['rate' => 10.1234]);
        $this->show($admin, 'grn')->assertSee('10.1234')->assertSee('10.00');   // the pending GRN's rate 10
        $this->assertStringContainsString(',10.1234,', $this->csv($admin, 'grn'));
        GrnLine::where('grn_id', $this->d['g1']->id)->update(['rate' => 10.5]);
        $this->show($admin, 'grn')->assertSee('10.50')->assertDontSee('10.5000');

        $this->d['a']->delete();
        $this->assertSoftDeleted($this->d['a']);
        $this->assertStringContainsString('ARC-0001,"Plant ARC-0001"', $this->csv($admin, 'grn'));
        $this->assertStringContainsString('ARC-0001,"Plant ARC-0001"', $this->csv($admin, 'stock-transfers'));
        $this->show($admin, 'stock-transfers')->assertSee('ARC-0001');
    }

    public function test_pdf_is_refused_above_its_cap(): void
    {
        $rows = [];
        $fy = FinancialYear::forDate(today());
        for ($i = 1; $i <= 2001; $i++) {
            $rows[] = ['unit_id' => $this->d['u1']->id, 'financial_year_id' => $fy->id, 'po_no' => 'BULK/'.$i, 'po_date' => today()->toDateString(), 'party_id' => $this->d['s1']->id,
                'supply_type' => 'Intra-state', 'status' => 'Draft', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('purchase_orders')->insert($chunk);
        }
        $this->actingAs($this->d['admin'])->get('/reports/purchase/po-summary/download?'.http_build_query($this->range(['format' => 'pdf'])))
            ->assertSessionHasErrors(['format' => 'Too many rows for PDF (more than 545 for this report). Use CSV or narrow the dates.']);
        $this->assertSame(2004, substr_count($this->csv($this->d['admin'], 'po-summary'), "\n"));   // header + 2,003 rows, streamed
    }

    public function test_stock_transfer_report_lines_filters_and_unit_scoping(): void
    {
        $admin = $this->d['admin'];
        $csv = $this->csv($admin, 'stock-transfers');
        $this->assertStringContainsString('"ST no",Date,From,To,"Item no","Item description",Quantity,UOM,Status,"Approved by","Approved on"', $csv);
        $this->assertStringContainsString('ST/26-27/0001,'.today()->format('d/m/Y').',"Unit 1 · Chakan","Unit 2 · Bhosari",ARC-0001,"Plant ARC-0001",20.000,NOS,Approved,'.$admin->name, $csv);
        $this->assertStringContainsString('ST/26-27/0002', $csv);
        $this->assertStringNotContainsString('ST/26-27/0003', $csv);   // cancelled
        $this->assertStringNotContainsString('ST/26-27/0002', $this->csv($admin, 'stock-transfers', ['item' => $this->d['a']->id]));
        $this->assertStringNotContainsString('ST/26-27/0001', $this->csv($admin, 'stock-transfers', ['from_unit' => $this->d['u2']->id]));
        $this->assertStringNotContainsString('ST/26-27/0002', $this->csv($admin, 'stock-transfers', ['status' => 'Approved']));

        $this->show($admin, 'stock-transfers')->assertSee(route('purchase.stock-transfers.show', $this->d['st1']), false)->assertSee('ST/26-27/0002');

        // A user of Unit 1 only sees transfers from or to Unit 1; one between other units is hidden.
        $u3 = Unit::create(['code' => 'U3', 'name' => 'Unit 3 · Talegaon']);
        $t = new StockTransfer;
        $t->forceFill(['financial_year_id' => FinancialYear::forDate(today())->id, 'transfer_no' => 'ST/26-27/0099', 'transfer_date' => today(), 'from_unit_id' => $this->d['u2']->id,
            'to_unit_id' => $u3->id, 'status' => 'Draft'])->save();
        (new StockTransferLine)->forceFill(['stock_transfer_id' => $t->id, 'line_no' => 1, 'item_id' => $this->d['a']->id, 'quantity' => 1])->save();
        $buyerCsv = $this->csv($this->buyer(), 'stock-transfers');
        $this->assertStringContainsString('ST/26-27/0001', $buyerCsv);
        $this->assertStringNotContainsString('ST/26-27/0099', $buyerCsv);
    }

    public function test_stock_ledger_running_balance_matches_stock_balances_and_the_stock_report(): void
    {
        $admin = $this->d['admin'];
        $a = $this->d['a'];
        // No item: a prompt on screen, an error for the download.
        $this->show($admin, 'stock-ledger')->assertSee('Choose an item to see its stock ledger.');
        $this->actingAs($admin)->get('/reports/purchase/stock-ledger/download?'.http_build_query($this->range(['format' => 'csv'])))->assertSessionHasErrors('item');

        $q = ['item' => $a->id, 'unit' => $this->d['u1']->id];
        $csv = $this->csv($admin, 'stock-ledger', $q);
        $d = today()->format('d/m/Y');
        $from = today()->subDays(5)->format('d/m/Y');
        $this->assertStringContainsString("$from,\"Opening balance\",,,,,100.000", $csv);
        $this->assertStringContainsString("$d,GRN,GRN/26-27/0001,\"Unit 1 · Chakan\",28.000,0.000,128.000", $csv);
        $this->assertStringContainsString("$d,\"Stock transfer out\",ST/26-27/0001,\"Unit 1 · Chakan\",0.000,20.000,108.000", $csv);
        $this->assertStringContainsString("$d,\"Sales invoice\",#9999,\"Unit 1 · Chakan\",0.000,5.000,103.000", $csv);
        $this->assertStringContainsString("$d,\"Closing balance\",,,,,103.000", $csv);
        $this->assertStringNotContainsString('#9998', $csv);   // after To
        // stock_balances holds 102 now (the later −1); closing at To = 102 + 1.
        $this->assertSame(102.0, (float) DB::table('stock_balances')->where('unit_id', $this->d['u1']->id)->where('item_id', $a->id)->value('quantity'));
        // Unit 2 sees the transfer coming in.
        $this->assertStringContainsString('"Stock transfer in",ST/26-27/0001,"Unit 2 · Bhosari",20.000,0.000,20.000', $this->csv($admin, 'stock-ledger', ['item' => $a->id, 'unit' => $this->d['u2']->id]));

        // On screen: opening, links, running balance; the second page carries the balance on.
        $page = $this->show($admin, 'stock-ledger', $q);
        $page->assertSee('Opening balance')->assertSee('Closing balance')->assertSee(route('purchase.grns.show', $this->d['g1']), false)
            ->assertSee(route('purchase.stock-transfers.show', $this->d['st1']), false);
        $fy = FinancialYear::forDate(today());
        for ($i = 0; $i < 25; $i++) {
            StockLedger::move($this->d['u1']->id, $a->id, today()->toDateString(), 1, 0, 'test_extra', $i);
        }
        $p2 = $this->show($admin, 'stock-ledger', $q + ['page' => 2]);
        $p2->assertDontSee('Opening balance')->assertSee('Closing balance')->assertSee('128');   // 103 + 25 = 128 closing (page 2 has rows 26–28)
        $this->assertStringContainsString("$d,\"Closing balance\",,,,,128.000", $this->csv($admin, 'stock-ledger', $q));

        // The shared Stock report agrees on the closing stock at To.
        $stock = $this->actingAs($admin)->get('/reports/sales/stock/download?'.http_build_query($this->range(['format' => 'csv', 'unit' => $this->d['u1']->id])))->streamedContent();
        $this->assertStringContainsString('ARC-0001,"Plant ARC-0001",NOS,150.000,,100.000,53.000,25.000,128.000,Yes', $stock);

        $this->actingAs($admin)->get('/reports/purchase/stock-ledger/download?'.http_build_query($this->range($q + ['format' => 'pdf'])))->assertOk();
    }

    public function test_grn_report_and_supplier_credit_debit_report(): void
    {
        $admin = $this->d['admin'];
        $csv = $this->csv($admin, 'grn');
        $this->assertStringContainsString('"GRN no","GRN date",Supplier,"Invoice no","Invoice date","Invoice amount","PO no","Item no","Item description","Invoice qty",Accepted,Rejected,Rate,Amount,Currency,Quality,Unit', $csv);
        $this->assertStringContainsString('GRN/26-27/0001,'.today()->format('d/m/Y').',"Green Leaf Growers",GLG-77,'.today()->format('d/m/Y').',330.40,PO/26-27/0001,ARC-0001,"Plant ARC-0001",30.000,28.000,2.000,10.0000,330.40,INR,Approved', $csv);
        $this->assertStringContainsString('"Pending match",,"Green Leaf Growers",GLG-78', $csv);   // dated by its invoice date
        $this->assertStringNotContainsString('CN-5', $csv);                                       // notes are in their own report
        $this->assertStringNotContainsString('GLG-78', $this->csv($admin, 'grn', ['match' => 'Matched']));
        $this->assertStringNotContainsString('GLG-77', $this->csv($admin, 'grn', ['item' => $this->d['b']->id]));
        $this->assertStringNotContainsString('GLG-77', $this->csv($admin, 'grn', ['quality' => 'Pending']));
        $this->show($admin, 'grn')->assertSee(route('purchase.grns.show', $this->d['g1']), false)->assertSee('Total · 2 GRNs')->assertSee('389.40');

        $notes = $this->csv($admin, 'credit-debit');
        $this->assertStringContainsString('GRN/26-27/0002,"Supplier credit note",'.today()->format('d/m/Y').',"Green Leaf Growers",27AAAPG1234K1Z5,CN-5,'.today()->format('d/m/Y').',100.00,18.00,118.00,INR', $notes);
        $this->assertStringContainsString('GRN/26-27/0003,"Supplier debit note"', $notes);
        $this->assertStringNotContainsString('CN-OLD', $notes);   // cancelled
        $this->assertStringNotContainsString('GLG-77', $notes);
        $this->assertStringNotContainsString('DN-9', $this->csv($admin, 'credit-debit', ['type' => 'Credit']));
        $this->assertStringNotContainsString('CN-5', $this->csv($admin, 'credit-debit', ['party' => $this->d['s2']->id]));
        $this->show($admin, 'credit-debit')->assertSee('Total · 1 credit note')->assertSee('Total · 1 debit note')->assertSee('236.00');
        $this->actingAs($admin)->get('/reports/purchase/grn/download?'.http_build_query($this->range(['format' => 'pdf'])))->assertOk();
        $this->actingAs($admin)->get('/reports/purchase/credit-debit/download?'.http_build_query($this->range(['format' => 'pdf'])))->assertOk();
    }

    public function test_rights_unit_scoping_pickers_and_download_limit(): void
    {
        $buyer = $this->buyer();
        $sales = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822422222', 'password' => 'Secret2026', 'status' => 'Active']);
        $sales->assignRole('Sales');
        $sales->units()->sync([$this->d['u1']->id]);

        foreach (['po-summary', 'grn', 'credit-debit', 'stock-transfers', 'stock-ledger'] as $r) {
            $this->actingAs($buyer)->get("/reports/purchase/$r")->assertOk();
            $this->actingAs($sales)->get("/reports/purchase/$r")->assertForbidden();
            $this->actingAs($sales)->get("/reports/purchase/$r/download?".http_build_query($this->range(['format' => 'csv'])))->assertForbidden();
        }
        // The shared Stock report opens for both; other Sales reports stay Sales only.
        $this->actingAs($buyer)->get('/reports/sales/stock?in=purchase')->assertOk()->assertSee('Purchase')->assertSee('Stock received comes in with the Purchase module')->assertDontSee('Until then');
        $this->actingAs($sales)->get('/reports/sales/stock')->assertOk();
        $this->actingAs($buyer)->get('/reports/sales/stock/download?'.http_build_query($this->range(['format' => 'csv'])))->assertOk();
        $this->actingAs($buyer)->get('/reports/sales/sales')->assertForbidden();

        // Unit scoping: the Unit 1 buyer does not see the Unit 2 PO.
        $csv = $this->csv($buyer, 'po-summary');
        $this->assertStringContainsString('PO/26-27/0001', $csv);
        $this->assertStringNotContainsString('PO/26-27/0002', $csv);

        // Pickers.
        $this->actingAs($buyer)->getJson('/reports/purchase/suppliers?q=kav')->assertOk()->assertJsonPath('0.label', 'Kaveri Seeds');
        $this->actingAs($buyer)->getJson('/reports/purchase/items?q=FCS')->assertOk()->assertJsonPath('0.id', $this->d['b']->id);
        $this->actingAs($sales)->getJson('/reports/purchase/items?q=FCS')->assertForbidden();

        // Downloads: 10 a minute (C10); Show is not limited. The count starts afresh here.
        cache()->flush();
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($buyer)->get('/reports/purchase/po-summary/download?'.http_build_query($this->range(['format' => 'csv'])))->assertOk();
        }
        $this->actingAs($buyer)->get('/reports/purchase/po-summary/download?'.http_build_query($this->range(['format' => 'csv'])))->assertStatus(429);
        $this->show($buyer, 'po-summary');
    }

    public function test_menu_links_all_six_purchase_reports_in_order_and_selects_purchase(): void
    {
        $buyer = $this->buyer();
        $this->assertCount(6, config('nursery.report_routes.Purchase'));
        $expect = ['reports.purchase.po-summary', 'reports.purchase.grn', 'reports.purchase.credit-debit', 'reports.sales.stock', 'reports.purchase.stock-transfers', 'reports.purchase.stock-ledger'];
        $this->assertSame($expect, config('nursery.report_routes.Purchase'));

        $html = $this->actingAs($buyer)->get(route('reports.purchase.po-summary', ['in' => 'purchase']))->assertOk()->getContent();
        foreach ($expect as $route) {
            $this->assertStringContainsString(e(route($route, ['in' => 'purchase'])), $html);
        }
        $html = $this->actingAs($buyer)->get(route('reports.sales.stock', ['in' => 'purchase']))->assertOk()->getContent();
        // Opened from the Purchase menu, the Purchase menu is open and its Stock report link is the current one.
        $this->assertStringContainsString('<a class="repl on" href="'.e(route('reports.sales.stock', ['in' => 'purchase'])).'"  aria-current="page" >', $html);
        $this->assertStringContainsString('Purchase reports', $html);
        $this->assertStringNotContainsString('<a class="repl on" href="'.e(route('reports.sales.stock', ['in' => 'sales'])).'"', $html);

        // The old sample addresses now open the real reports, keeping ?in.
        foreach ($expect as $i => $route) {
            $this->actingAs($buyer)->get("/app/reports/Purchase-$i?in=purchase")->assertRedirect(route($route, ['in' => 'purchase']));
        }
    }
}
