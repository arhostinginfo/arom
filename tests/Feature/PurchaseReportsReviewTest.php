<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\Grn;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tester review of the six Purchase reports (27/10/2026). Every document is made through the app's own screens:
 * PO → submit → approve, GRN save / match → quality approval (stock in), pending-match GRN, deleted GRN, cancelled GRN,
 * supplier credit / debit notes, stock transfer approve / cancel, sales invoices (stock out) and a cancelled invoice.
 * Then the exact figures of each report are checked on screen and in the CSV, and the ledger against stock_balances and
 * the shared Stock report. Test database only (SQLite :memory:).
 *
 * Timeline (D1 < D2 < D3 < D4, all in the current financial year), item A = ARC-1001 (100, GST 18%), B = FCS-2002 (40, GST 12%):
 *  D1  PO1 (Unit 1, Green Leaf, A 100 + B 50) approved; PO2 (Unit 2, Kaveri, A 10) draft; PO3 approved then cancelled;
 *      PO4 draft deleted. GRN1 against PO1: A 60, B 50 = 9,320 matched; quality approved with 10 of A rejected → +50 A, +50 B.
 *  D2  INV1 A 15 (out); INV-X A 4 cancelled; ST1 Unit 1 → Unit 2 A 20 approved; ST2 B 5 approved then cancelled;
 *      GRN2 A 10 pending match (amount 1,000); GRN3 pending then deleted; GRN4 A 10 matched, approved (+10), cancelled (−10).
 *  D3  INV2 A 5; supplier credit note CN-7 (A 2 = 236, intra-state); debit note DN-8 from Kaveri (A 1 = 118, IGST);
 *      credit note CN-9 cancelled.
 *  D4  INV3 A 3 (after To).
 */
class PurchaseReportsReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private array $d = [];

    private array $days = [];

    protected function setUp(): void
    {
        parent::setUp();
        $fy = FinancialYear::forDate(now());
        $base = $fy->starts_on->copy()->addDays(20)->setTime(10, 0);
        $this->days = ['D1' => $base->copy(), 'D2' => $base->copy()->addDays(7), 'D3' => $base->copy()->addDays(14), 'D4' => $base->copy()->addDays(20)];

        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        [$u1, $u2] = Unit::orderBy('id')->get()->all();
        $inr = Currency::defaultId();
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '0602', 'type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn (string $no, float $price, $tax, $min) => Item::create(['description' => 'Plant '.$no, 'item_no' => $no, 'type' => 'Both', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'currency_id' => $inr, 'min_stock' => $min, 'status' => 'Active']);
        $this->d = [
            'u1' => $u1, 'u2' => $u2,
            's1' => Party::create(['name' => 'Green Leaf Growers', 'gstin' => '27AAAPG1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Pune', 'status' => 'Active', 'currency_id' => $inr]),
            's2' => Party::create(['name' => 'Kaveri Seeds', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Supplier', 'billing_address' => 'Bengaluru', 'status' => 'Active', 'currency_id' => $inr]),
            // A supplier whose name a spreadsheet would run as a formula (CSV injection check).
            's3' => Party::create(['name' => '=HYPERLINK("http://x.example","Click")', 'gstin' => '27AAAPH1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Pune', 'status' => 'Active', 'currency_id' => $inr]),
            'cust' => Party::create(['name' => 'Apex Landscapes', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan', 'shipping_address' => 'Chakan', 'status' => 'Active', 'currency_id' => $inr]),
            'a' => $mk('ARC-1001', 100, $gst18, 25),
            'b' => $mk('FCS-2002', 40, $gst12, null),
        ];
        $this->buildDocuments();
    }

    // ---- Users ----

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role, array $units): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98225'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync(array_map(fn (Unit $x) => $x->id, $units));

        return $u;
    }

    private function buyer(): User
    {
        return $this->user('rahul@company.example', 'Purchase', [$this->d['u1']]);
    }

    private function buyer2(): User
    {
        return $this->user('meera@company.example', 'Purchase', [$this->d['u2']]);
    }

    private function sales(): User
    {
        return $this->user('priya@company.example', 'Sales', [$this->d['u1']]);
    }

    // ---- Documents through the app ----

    private function po(User $as, Party $party, array $lines, bool $approve = true): PurchaseOrder
    {
        $this->actingAs($as)->post('/purchase/orders', ['party_id' => $party->id, 'po_date' => today()->format('d/m/Y'), 'billing_address' => 'Pune', 'shipping_address' => 'Pune',
            'lines_json' => json_encode($lines)])->assertSessionHasNoErrors();
        $po = PurchaseOrder::latest('id')->first();
        if ($approve) {
            $this->actingAs($as)->post("/purchase/orders/{$po->id}/submit")->assertSessionHasNoErrors();
            if ($po->fresh()->status === PurchaseOrder::PENDING) {
                $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/approve")->assertSessionHasNoErrors();
            }
            $this->assertSame(PurchaseOrder::APPROVED, $po->fresh()->status);
        }

        return $po->fresh('lines');
    }

    private function poLine(PurchaseOrder $po, int $n, $qty): array
    {
        $l = $po->lines->firstWhere('line_no', $n);

        return ['purchase_order_id' => (string) $po->id, 'purchase_order_line_id' => (string) $l->id, 'quantity' => (string) $qty, 'rate' => ''];
    }

    private function grn(array $lines, array $over): Grn
    {
        $this->actingAs($this->buyer())->post('/purchase/grns', array_merge(['invoice_type' => 'Purchase', 'party_id' => $this->d['s1']->id, 'invoice_no' => 'X',
            'invoice_date' => today()->format('d/m/Y'), 'invoice_amount' => '0', 'transporter_id' => '', 'vehicle_no' => 'MH 12 AB 1234', 'eway_bill_no' => '',
            'eway_bill_date' => '', 'customer_id' => '', 'sales_order_id' => '', 'remarks' => '', 'intent' => 'pending', 'lines_json' => json_encode($lines)], $over))
            ->assertSessionHasNoErrors();

        return Grn::latest('id')->first();
    }

    private function invoice(float $qty, ?User $as = null): SalesInvoice
    {
        $this->actingAs($as ?? $this->sales())->post('/sales/invoices', ['invoice_type' => 'Sales', 'invoice_date' => today()->format('d/m/Y'), 'party_id' => $this->d['cust']->id,
            'sales_order_id' => '', 'shipping_address' => 'Chakan', 'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => 'MH 14 AB 1234', 'distance_km' => '42',
            'pf_charges' => '', 'lines' => [['item_id' => $this->d['a']->id, 'quantity' => (string) $qty, 'rate' => '']]])->assertSessionHasNoErrors();

        return SalesInvoice::latest('id')->first();
    }

    private function transfer(Item $item, float $qty): StockTransfer
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase/stock-transfers', ['transfer_date' => today()->format('d/m/Y'), 'from_unit_id' => $this->d['u1']->id, 'to_unit_id' => $this->d['u2']->id,
            'source_type' => 'Purchase', 'remarks' => '', 'lines' => [['item_id' => $item->id, 'quantity' => (string) $qty, 'remarks' => '']], '_form' => 'transfer'])->assertSessionHasNoErrors();
        $t = StockTransfer::latest('id')->first();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();

        return $t->fresh();
    }

    private function buildDocuments(): void
    {
        $d = &$this->d;
        $admin = $this->admin();
        $A = (string) $d['a']->id;
        $B = (string) $d['b']->id;

        // ---- D1 ----
        $this->travelTo($this->days['D1']);
        $d['po1'] = $this->po($this->buyer(), $d['s1'], [['item_id' => $A, 'quantity' => '100', 'rate' => ''], ['item_id' => $B, 'quantity' => '50', 'rate' => '']]);
        $d['po2'] = $this->po($this->buyer2(), $d['s2'], [['item_id' => $A, 'quantity' => '10', 'rate' => '']], false);
        $d['po3'] = $this->po($this->buyer(), $d['s3'], [['item_id' => $A, 'quantity' => '5', 'rate' => '']]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$d['po3']->id}/cancel", ['remarks' => 'Not needed'])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::CANCELLED, $d['po3']->fresh()->status);
        $d['po4'] = $this->po($this->buyer(), $d['s1'], [['item_id' => $A, 'quantity' => '7', 'rate' => '']], false);
        $this->actingAs($this->buyer())->delete("/purchase/orders/{$d['po4']->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('purchase_orders', ['id' => $d['po4']->id]);

        // GRN1: A 60 × 100 + 18% = 7,080; B 50 × 40 + 12% = 2,240; total 9,320. Quality: 10 of A rejected.
        $d['g1'] = $this->grn([$this->poLine($d['po1'], 1, 60), $this->poLine($d['po1'], 2, 50)], ['invoice_no' => 'GLG-101', 'invoice_amount' => '9320.00', 'intent' => 'match']);
        $this->assertSame(Grn::MATCHED, $d['g1']->match_status);
        $aLine = $d['g1']->lines()->where('item_id', $d['a']->id)->first();
        $this->actingAs($admin)->post("/purchase/grns/{$d['g1']->id}/quality", ['result' => 'Approved', 'remarks' => 'Checked', 'rejected' => [$aLine->id => '10']])
            ->assertSessionHasNoErrors();
        $d['g1']->refresh();

        // ---- D2 ----
        $this->travelTo($this->days['D2']);
        $d['inv1'] = $this->invoice(15);
        $d['invX'] = $this->invoice(4);
        $this->actingAs($admin)->post("/sales/invoices/{$d['invX']->id}/cancel", ['cancel_reason' => 'Raised in error'])->assertSessionHasNoErrors();
        $d['st1'] = $this->transfer($d['a'], 20);
        $d['st2'] = $this->transfer($d['b'], 5);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$d['st2']->id}/cancel", ['remarks' => 'Sent by mistake'])->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::CANCELLED, $d['st2']->fresh()->status);
        $d['g2'] = $this->grn([$this->poLine($d['po1'], 1, 10)], ['invoice_no' => 'GLG-102', 'invoice_amount' => '1000.00']);
        $this->assertSame(Grn::PENDING_MATCH, $d['g2']->match_status);
        $g3 = $this->grn([$this->poLine($d['po1'], 1, 3)], ['invoice_no' => 'GLG-DEL', 'invoice_amount' => '1']);
        $this->actingAs($this->buyer())->delete("/purchase/grns/{$g3->id}")->assertSessionHasNoErrors();
        $d['g4'] = $this->grn([$this->poLine($d['po1'], 1, 10)], ['invoice_no' => 'GLG-CAN', 'invoice_amount' => '1180.00', 'intent' => 'match']);
        $this->actingAs($admin)->post("/purchase/grns/{$d['g4']->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/purchase/grns/{$d['g4']->id}/cancel", ['remarks' => 'Wrong invoice'])->assertSessionHasNoErrors();
        $this->assertSame(Grn::CANCELLED, $d['g4']->fresh()->status);

        // ---- D3 (the To date of the main range) ----
        $this->travelTo($this->days['D3']);
        $d['inv2'] = $this->invoice(5);
        $d['cn'] = $this->grn([['item_id' => $A, 'quantity' => '2', 'rate' => '100']], ['invoice_type' => 'Credit', 'invoice_no' => 'CN-7', 'invoice_amount' => '236', 'intent' => 'match']);
        $d['dn'] = $this->grn([['item_id' => $A, 'quantity' => '1', 'rate' => '100']], ['invoice_type' => 'Debit', 'party_id' => $d['s2']->id, 'invoice_no' => 'DN-8', 'invoice_amount' => '118', 'intent' => 'match']);
        $cn9 = $this->grn([['item_id' => $A, 'quantity' => '1', 'rate' => '100']], ['invoice_type' => 'Credit', 'invoice_no' => 'CN-9', 'invoice_amount' => '118', 'intent' => 'match']);
        $this->actingAs($admin)->post("/purchase/grns/{$cn9->id}/cancel", ['remarks' => 'Duplicate'])->assertSessionHasNoErrors();

        // ---- D4 (after To) ----
        $this->travelTo($this->days['D4']);
        $d['inv3'] = $this->invoice(3);

        foreach (['g1', 'g2', 'g4', 'cn', 'dn', 'st1', 'st2', 'po1', 'po2', 'po3'] as $k) {
            $d[$k] = $d[$k]->fresh();
        }
    }

    // ---- Helpers ----

    private function day(string $k): string
    {
        return $this->days[$k]->format('d/m/Y');
    }

    private function range(string $from, string $to, array $q = []): array
    {
        return ['from' => $this->day($from), 'to' => $this->day($to)] + $q;
    }

    /** CSV text of a report; the download limit is reset first so a test can read many files. */
    private function csv(User $u, string $report, array $q): string
    {
        cache()->flush();

        return $this->actingAs($u)->get("/reports/purchase/$report/download?".http_build_query($q + ['format' => 'csv']))->assertOk()->streamedContent();
    }

    private function stockCsv(User $u, array $q): string
    {
        cache()->flush();

        return $this->actingAs($u)->get('/reports/sales/stock/download?'.http_build_query($q + ['format' => 'csv']))->assertOk()->streamedContent();
    }

    private function show(User $u, string $report, array $q)
    {
        return $this->actingAs($u)->get("/reports/purchase/$report?".http_build_query($q + ['show' => 1]))->assertOk()->assertDontSee('Check the options marked in red');
    }

    /** CSV data lines (BOM and header removed). */
    private function lines(string $csv): array
    {
        $rows = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $csv)), fn ($l) => $l !== ''));
        array_shift($rows);

        return $rows;
    }

    // ---- Sanity of the data made through the app ----

    public function test_the_documents_made_through_the_app_left_the_expected_stock(): void
    {
        $bal = fn (Unit $u, Item $i) => (float) DB::table('stock_balances')->where('unit_id', $u->id)->where('item_id', $i->id)->value('quantity');
        $this->assertSame(7.0, $bal($this->d['u1'], $this->d['a']));    // 50 − 15 − 20 − 5 − 3
        $this->assertSame(20.0, $bal($this->d['u2'], $this->d['a']));
        $this->assertSame(50.0, $bal($this->d['u1'], $this->d['b']));   // ST2 cancelled
        $this->assertSame(0.0, $bal($this->d['u2'], $this->d['b']));
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->where('source_id', $this->d['g4']->id)->count());
        $this->assertSame(0, StockMovement::where('source_type', 'sales_invoice')->where('source_id', $this->d['invX']->id)->count());
        $this->assertSame(['50.000', '50.000'], $this->d['po1']->lines()->orderBy('line_no')->pluck('received_quantity')->all());
        $this->assertSame($this->days['D1']->toDateString(), $this->d['g1']->grn_date->toDateString());
        $this->assertNull($this->d['g2']->grn_date);
    }

    // ---- PO summary ----

    public function test_po_summary_exact_figures_on_screen_and_csv_with_unit_scoping(): void
    {
        $admin = $this->admin();
        $d = $this->d;
        $q = $this->range('D1', 'D3');
        $rows = $this->lines($this->csv($admin, 'po-summary', $q));
        $this->assertSame([
            $d['po1']->po_no.','.$this->day('D1').',"Green Leaf Growers",2,12000.00,2040.00,14040.00,INR,66.7,"'.$d['po1']->status.'","Unit 1 · Chakan"',
            $d['po2']->po_no.','.$this->day('D1').',"Kaveri Seeds",1,1000.00,180.00,1180.00,INR,0.0,Draft,"Unit 2 · Bhosari"',
        ], $rows);   // PO3 cancelled and PO4 deleted are left out
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $d['po1']->status);

        $page = $this->show($admin, 'po-summary', $q);
        $page->assertSee('12,000.00')->assertSee('14,040.00')->assertSee('66.7')
            ->assertSee('Total · 2 POs')->assertSee('13,000.00')->assertSee('2,220.00')->assertSee('15,220.00')
            ->assertSee(route('purchase.orders.show', $d['po1']), false)->assertDontSee($d['po3']->po_no)->assertDontSee($d['po4']->po_no);

        // Cancelled only on request; the deleted draft never.
        $cancelled = $this->lines($this->csv($admin, 'po-summary', $q + ['status' => 'Cancelled']));
        $this->assertCount(1, $cancelled);
        $this->assertStringStartsWith($d['po3']->po_no.',', $cancelled[0]);
        $this->assertStringNotContainsString($d['po4']->po_no, $this->csv($admin, 'po-summary', $q + ['status' => 'Draft']));

        // Unit 1 buyer: only PO1, totals of PO1 only. Unit 2 buyer: only PO2.
        $this->assertSame([$rows[0]], $this->lines($this->csv($this->buyer(), 'po-summary', $q)));
        $this->show($this->buyer(), 'po-summary', $q)->assertSee('Total · 1 PO')->assertDontSee('1,180.00')->assertDontSee('15,220.00');   // PO numbers are per unit: PO2 is also .../0001
        $this->assertSame([$rows[1]], $this->lines($this->csv($this->buyer2(), 'po-summary', $q)));

        // Period boundary: a range ending the day before D1 has none; starting on D1 = To has both.
        $this->assertSame([], $this->lines($this->csv($admin, 'po-summary', ['from' => $this->days['D1']->copy()->subDays(3)->format('d/m/Y'), 'to' => $this->days['D1']->copy()->subDay()->format('d/m/Y')])));
        $this->assertCount(2, $this->lines($this->csv($admin, 'po-summary', $this->range('D1', 'D1'))));
    }

    // ---- GRN report ----

    public function test_grn_report_exact_lines_pending_match_dated_by_invoice_date_and_exclusions(): void
    {
        $admin = $this->admin();
        $d = $this->d;
        $u1 = '"Unit 1 · Chakan"';
        $rows = $this->lines($this->csv($admin, 'grn', $this->range('D1', 'D3')));
        $g1 = $d['g1']->grn_no.','.$this->day('D1').',"Green Leaf Growers",GLG-101,'.$this->day('D1').',9320.00,'.$d['po1']->po_no;
        $this->assertSame([
            $g1.',ARC-1001,"Plant ARC-1001",60.000,50.000,10.000,100.0000,7080.00,INR,Approved,'.$u1,
            $g1.',FCS-2002,"Plant FCS-2002",50.000,50.000,0.000,40.0000,2240.00,INR,Approved,'.$u1,
            '"Pending match",,"Green Leaf Growers",GLG-102,'.$this->day('D2').',1000.00,'.$d['po1']->po_no.',ARC-1001,"Plant ARC-1001",10.000,0.000,0.000,100.0000,1180.00,INR,Pending,'.$u1,
        ], $rows);   // cancelled GLG-CAN, deleted GLG-DEL and the supplier notes are left out

        $this->show($admin, 'grn', $this->range('D1', 'D3'))
            ->assertSee('Total · 2 GRNs')->assertSee('10,500.00')->assertSee('7,080.00')
            ->assertSee(route('purchase.grns.show', $d['g1']), false)->assertSee(route('purchase.grns.show', $d['g2']), false)
            ->assertDontSee('GLG-CAN')->assertDontSee('GLG-DEL')->assertDontSee('CN-7');

        // Pending match is dated by its invoice date (D2); the matched GRN by its GRN date (D1).
        $d2d3 = $this->csv($admin, 'grn', $this->range('D2', 'D3'));
        $this->assertStringContainsString('GLG-102', $d2d3);
        $this->assertStringNotContainsString('GLG-101', $d2d3);
        $d1 = $this->csv($admin, 'grn', $this->range('D1', 'D1'));
        $this->assertStringContainsString('GLG-101', $d1);
        $this->assertStringNotContainsString('GLG-102', $d1);

        // Filters.
        $this->assertCount(2, $this->lines($this->csv($admin, 'grn', $this->range('D1', 'D3', ['match' => Grn::MATCHED]))));
        $this->assertCount(1, $this->lines($this->csv($admin, 'grn', $this->range('D1', 'D3', ['quality' => Grn::QA_PENDING]))));
        $this->assertCount(1, $this->lines($this->csv($admin, 'grn', $this->range('D1', 'D3', ['item' => $d['b']->id]))));
        $this->assertSame([], $this->lines($this->csv($admin, 'grn', $this->range('D1', 'D3', ['party' => $d['s2']->id]))));

        // Unit scoping: the Unit 2 buyer sees none of the Unit 1 GRNs; a Unit 1 filter is refused.
        $this->assertSame([], $this->lines($this->csv($this->buyer2(), 'grn', $this->range('D1', 'D3'))));
        cache()->flush();
        $this->actingAs($this->buyer2())->get('/reports/purchase/grn/download?'.http_build_query($this->range('D1', 'D3', ['unit' => $d['u1']->id, 'format' => 'csv'])))
            ->assertSessionHasErrors('unit');
    }

    // ---- Credit/Debit (supplier) ----

    public function test_credit_debit_report_totals_per_type(): void
    {
        $admin = $this->admin();
        $d = $this->d;
        $rows = $this->lines($this->csv($admin, 'credit-debit', $this->range('D2', 'D3')));
        $this->assertSame([
            $d['cn']->grn_no.',"Supplier credit note",'.$this->day('D3').',"Green Leaf Growers",27AAAPG1234K1Z5,CN-7,'.$this->day('D3').',200.00,36.00,236.00,INR,"Unit 1 · Chakan"',
            $d['dn']->grn_no.',"Supplier debit note",'.$this->day('D3').',"Kaveri Seeds",29AABCK1234F1Z5,DN-8,'.$this->day('D3').',100.00,18.00,118.00,INR,"Unit 1 · Chakan"',
        ], $rows);   // CN-9 cancelled, purchase GRNs left out
        $this->assertSame('18.00', (string) $d['dn']->igst_amount);

        $this->show($admin, 'credit-debit', $this->range('D2', 'D3'))
            ->assertSee('Total · 1 credit note')->assertSee('Total · 1 debit note')->assertSee('236.00')->assertSee('118.00')
            ->assertDontSee('CN-9')->assertDontSee('GLG-101')->assertSee(route('purchase.grns.show', $d['cn']), false);
        $this->assertSame([$rows[0]], $this->lines($this->csv($admin, 'credit-debit', $this->range('D2', 'D3', ['type' => 'Credit']))));
        $this->assertSame([$rows[1]], $this->lines($this->csv($admin, 'credit-debit', $this->range('D2', 'D3', ['party' => $d['s2']->id]))));
        // The day before D3 has no notes (D3 boundary).
        $this->assertSame([], $this->lines($this->csv($admin, 'credit-debit', $this->range('D1', 'D2'))));
    }

    // ---- Stock transfers ----

    public function test_stock_transfer_report_rows_cancelled_and_scoping(): void
    {
        $admin = $this->admin();
        $d = $this->d;
        $at = $d['st1']->approved_at->format(config('nursery.datetime_format'));
        $rows = $this->lines($this->csv($admin, 'stock-transfers', $this->range('D2', 'D3')));
        $this->assertSame([$d['st1']->transfer_no.','.$this->day('D2').',"Unit 1 · Chakan","Unit 2 · Bhosari",ARC-1001,"Plant ARC-1001",20.000,NOS,Approved,'.$admin->name.',"'.$at.'"'], $rows);
        $cancelled = $this->lines($this->csv($admin, 'stock-transfers', $this->range('D2', 'D3', ['status' => 'Cancelled'])));
        $this->assertCount(1, $cancelled);
        $this->assertStringStartsWith($d['st2']->transfer_no.','.$this->day('D2').',"Unit 1 · Chakan","Unit 2 · Bhosari",FCS-2002,"Plant FCS-2002",5.000,NOS,Cancelled', $cancelled[0]);
        $this->show($admin, 'stock-transfers', $this->range('D2', 'D3'))->assertSee(route('purchase.stock-transfers.show', $d['st1']), false)->assertDontSee($d['st2']->transfer_no);
        // A user of the destination unit only sees it too (from or to my units).
        $this->assertSame($rows, $this->lines($this->csv($this->buyer2(), 'stock-transfers', $this->range('D2', 'D3'))));
        $this->assertSame([], $this->lines($this->csv($admin, 'stock-transfers', $this->range('D3', 'D3'))));
    }

    // ---- Stock ledger ----

    public function test_stock_ledger_opening_running_closing_match_stock_balances_and_stock_report(): void
    {
        $admin = $this->admin();
        $d = $this->d;
        $u1 = '"Unit 1 · Chakan"';
        $q = $this->range('D2', 'D3', ['item' => $d['a']->id, 'unit' => $d['u1']->id]);
        // Opening at D2 = GRN1 +50 on D1 (before From). D2: INV1 −15, ST1 out −20 (cancelled INV-X and GRN4 left no rows).
        // D3 (= To): INV2 −5. D4 (after To): INV3 −3 is not listed but is in stock_balances (7), so closing = 7 + 3 = 10.
        $this->assertSame([
            $this->day('D2').',"Opening balance",,,,,50.000',
            $this->day('D2').',"Sales invoice",'.$d['inv1']->invoice_no.','.$u1.',0.000,15.000,35.000',
            $this->day('D2').',"Stock transfer out",'.$d['st1']->transfer_no.','.$u1.',0.000,20.000,15.000',
            $this->day('D3').',"Sales invoice",'.$d['inv2']->invoice_no.','.$u1.',0.000,5.000,10.000',
            $this->day('D3').',"Closing balance",,,,,10.000',
        ], $this->lines($this->csv($admin, 'stock-ledger', $q)));

        // The shared Stock report for the same unit and dates: opening 50, in 0, out 40, closing 10, below the minimum of 25.
        $stock = $this->stockCsv($admin, $this->range('D2', 'D3', ['unit' => $d['u1']->id]));
        $this->assertStringContainsString('ARC-1001,"Plant ARC-1001",NOS,25.000,,50.000,0.000,40.000,10.000,Yes', $stock);
        $this->assertStringContainsString('FCS-2002,"Plant FCS-2002",NOS,,,50.000,0.000,0.000,50.000,No', $stock);

        // From D1: the GRN comes in on the From date itself, opening 0.
        $fromD1 = $this->lines($this->csv($admin, 'stock-ledger', $this->range('D1', 'D3', ['item' => $d['a']->id, 'unit' => $d['u1']->id])));
        $this->assertSame($this->day('D1').',"Opening balance",,,,,0.000', $fromD1[0]);
        $this->assertSame($this->day('D1').',GRN,'.$d['g1']->grn_no.','.$u1.',50.000,0.000,50.000', $fromD1[1]);
        $this->assertSame($this->day('D3').',"Closing balance",,,,,10.000', end($fromD1));
        // To D4: closing equals stock on hand now.
        $toD4 = $this->lines($this->csv($admin, 'stock-ledger', $this->range('D2', 'D4', ['item' => $d['a']->id, 'unit' => $d['u1']->id])));
        $this->assertSame($this->day('D4').',"Sales invoice",'.$d['inv3']->invoice_no.','.$u1.',0.000,3.000,7.000', $toD4[4]);
        $this->assertSame($this->day('D4').',"Closing balance",,,,,7.000', $toD4[5]);

        // All my units (admin: both): the transfer is out of Unit 1 and into Unit 2, opening 50, closing 27 + 3 = 30.
        $all = $this->lines($this->csv($admin, 'stock-ledger', $this->range('D2', 'D3', ['item' => $d['a']->id])));
        $this->assertSame([
            $this->day('D2').',"Opening balance",,,,,50.000',
            $this->day('D2').',"Sales invoice",'.$d['inv1']->invoice_no.','.$u1.',0.000,15.000,35.000',
            $this->day('D2').',"Stock transfer out",'.$d['st1']->transfer_no.','.$u1.',0.000,20.000,15.000',
            $this->day('D2').',"Stock transfer in",'.$d['st1']->transfer_no.',"Unit 2 · Bhosari",20.000,0.000,35.000',
            $this->day('D3').',"Sales invoice",'.$d['inv2']->invoice_no.','.$u1.',0.000,5.000,30.000',
            $this->day('D3').',"Closing balance",,,,,30.000',
        ], $all);
        $this->assertStringContainsString('ARC-1001,"Plant ARC-1001",NOS,25.000,,50.000,20.000,40.000,30.000,No', $this->stockCsv($admin, $this->range('D2', 'D3')));

        // Item B: the cancelled transfer leaves no rows; opening 50 = closing.
        $this->assertSame([$this->day('D2').',"Opening balance",,,,,50.000', $this->day('D3').',"Closing balance",,,,,50.000'],
            $this->lines($this->csv($admin, 'stock-ledger', $this->range('D2', 'D3', ['item' => $d['b']->id, 'unit' => $d['u1']->id]))));

        // On screen: opening, running balance, closing, links to the documents.
        $this->show($admin, 'stock-ledger', $q)->assertSeeInOrder(['Opening balance', '50', $d['inv1']->invoice_no, '35', $d['st1']->transfer_no, '15', $d['inv2']->invoice_no, '10', 'Closing balance', '10'])
            ->assertSee(route('sales.invoices.show', $d['inv1']), false)->assertSee(route('purchase.stock-transfers.show', $d['st1']), false);
        // A Purchase user without the tax invoice right sees the invoice number without a link.
        $this->show($this->buyer(), 'stock-ledger', $q)->assertSee($d['inv1']->invoice_no)->assertDontSee(route('sales.invoices.show', $d['inv1']), false);

        // Unit scoping: the Unit 1 buyer's "all my units" is Unit 1 only; Unit 2 is refused.
        $this->assertStringContainsString('"Closing balance",,,,,10.000', $this->csv($this->buyer(), 'stock-ledger', $this->range('D2', 'D3', ['item' => $d['a']->id])));
        cache()->flush();
        $this->actingAs($this->buyer())->get('/reports/purchase/stock-ledger/download?'.http_build_query($this->range('D2', 'D3', ['item' => $d['a']->id, 'unit' => $d['u2']->id, 'format' => 'csv'])))
            ->assertSessionHasErrors('unit');
    }

    public function test_stock_ledger_pages_carry_the_balance_and_the_pdf_cap_counts_movements_only(): void
    {
        $admin = $this->admin();
        $a = $this->d['a'];
        $u1 = $this->d['u1'];
        // 2,000 more movements on D3 of +1 each (written in bulk with their balance, the way StockLedger keeps both).
        $date = $this->days['D3']->copy()->startOfDay()->toDateTimeString();
        $rows = [];
        for ($i = 1; $i <= 1997; $i++) {
            $rows[] = ['unit_id' => $u1->id, 'item_id' => $a->id, 'movement_date' => $date, 'quantity_in' => 1, 'quantity_out' => 0, 'source_type' => 'test_bulk', 'source_id' => $i, 'created_at' => now()];
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }
        DB::table('stock_balances')->where('unit_id', $u1->id)->where('item_id', $a->id)->increment('quantity', 1997);
        $q = $this->range('D2', 'D3', ['item' => $a->id, 'unit' => $u1->id]);

        // 3 + 1,997 = 2,000 movements: CSV has opening + 2,000 + closing; closing = 10 + 1,997 and equals the Stock report.
        $csv = $this->lines($this->csv($admin, 'stock-ledger', $q));
        $this->assertCount(2002, $csv);
        $this->assertSame($this->day('D3').',"Closing balance",,,,,2007.000', end($csv));
        $this->assertSame($this->day('D3').',"Test bulk",#1997,"Unit 1 · Chakan",1.000,0.000,2007.000', $csv[2000]);
        $this->assertStringContainsString('ARC-1001,"Plant ARC-1001",NOS,25.000,,50.000,1997.000,40.000,2007.000,No', $this->stockCsv($admin, $this->range('D2', 'D3', ['unit' => $u1->id])));

        // Screen page 41 of 25 rows (rows 1,001–1,025) starts from the balance after row 1,000.
        $p = $this->show($admin, 'stock-ledger', $q + ['page' => 41]);
        $p->assertDontSee('Opening balance')->assertDontSee('Closing balance');
        // Rows: INV1 (35), ST1 (15), INV2 (10), then +1 each; row 1,001 is bulk #998 → 10 + 998 = 1,008.
        $p->assertSee('#998')->assertSee('1,008')->assertSee('#1022')->assertSee('1,032');
        $this->show($admin, 'stock-ledger', $q + ['page' => 80])->assertSee('Closing balance')->assertSee('2,007');

        // PDF cap (C9, revised 27/10/2026): it scales with the columns (ReportPdf), 857 movements for the 7-column ledger;
        // opening and closing are not counted. 2,000 movements are refused, naming the cap.
        $cap = \App\Http\Controllers\Reports\PurchaseReportController::pdfCap('stock-ledger');
        $this->assertSame(857, $cap);
        cache()->flush();
        $this->actingAs($admin)->from('/reports/purchase/stock-ledger')->get('/reports/purchase/stock-ledger/download?'.http_build_query($q + ['format' => 'pdf']))
            ->assertRedirect('/reports/purchase/stock-ledger')->assertSessionHasErrors(['format' => \App\Support\ReportPdf::tooMany($cap)]);
        // Down to exactly the cap: 3 + (cap − 3) bulk movements. The renderer is replaced here to keep the test quick.
        $drop = 1997 - ($cap - 3);
        DB::table('stock_movements')->where('source_type', 'test_bulk')->where('source_id', '>', $cap - 3)->delete();
        DB::table('stock_balances')->where('unit_id', $u1->id)->where('item_id', $a->id)->decrement('quantity', $drop);
        $closing = number_format(10 + $cap - 3, 3, '.', '');
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('setOption')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('%PDF-stub', 200, ['Content-Type' => 'application/pdf']));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->withArgs(fn ($view, $data) => $view === 'pdf.report' && count($data['rows']) === $cap + 2 && $data['rows'][0][1] === 'Opening balance' && $data['rows'][$cap + 1][6] === $closing)
            ->andReturn($pdf);
        cache()->flush();
        $this->actingAs($admin)->get('/reports/purchase/stock-ledger/download?'.http_build_query($q + ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        DB::table('stock_movements')->insert(['unit_id' => $u1->id, 'item_id' => $a->id, 'movement_date' => $date, 'quantity_in' => 1, 'quantity_out' => 0, 'source_type' => 'test_bulk', 'source_id' => 5000, 'created_at' => now()]);
        cache()->flush();
        $this->actingAs($admin)->from('/reports/purchase/stock-ledger')->get('/reports/purchase/stock-ledger/download?'.http_build_query($q + ['format' => 'pdf']))
            ->assertRedirect('/reports/purchase/stock-ledger')->assertSessionHasErrors('format');
    }

    // ---- Rights, pickers, throttle, CSV injection, routes ----

    public function test_sales_only_role_is_refused_purchase_reports_and_both_open_the_shared_stock_report(): void
    {
        $sales = $this->sales();
        $buyer = $this->buyer();
        $accounts = $this->user('anil@company.example', 'Accounts', [$this->d['u1']]);
        $q = $this->range('D1', 'D3', ['format' => 'csv', 'item' => $this->d['a']->id]);
        foreach (['po-summary', 'grn', 'credit-debit', 'stock-transfers', 'stock-ledger'] as $r) {
            $this->actingAs($sales)->get("/reports/purchase/$r")->assertForbidden();
            $this->actingAs($sales)->get("/reports/purchase/$r?".http_build_query($q + ['show' => 1]))->assertForbidden();
            $this->actingAs($sales)->get("/reports/purchase/$r/download?".http_build_query($q))->assertForbidden();
            $this->actingAs($sales)->get("/reports/purchase/$r/download?".http_build_query(['format' => 'pdf'] + $q))->assertForbidden();
            $this->actingAs($buyer)->get("/reports/purchase/$r")->assertOk();
        }
        $this->actingAs($sales)->getJson('/reports/purchase/suppliers?q=gre')->assertForbidden();
        $this->actingAs($sales)->getJson('/reports/purchase/items?q=ARC')->assertForbidden();
        $this->actingAs($accounts)->getJson('/reports/purchase/suppliers?q=gre')->assertForbidden();
        $this->actingAs($buyer)->getJson('/reports/purchase/suppliers?q=gre')->assertOk()->assertJsonPath('0.label', 'Green Leaf Growers');
        $this->actingAs($buyer)->getJson('/reports/purchase/items?q=ARC')->assertOk()->assertJsonPath('0.id', $this->d['a']->id);

        // Shared Stock report: Sales and Purchase both; Accounts neither.
        cache()->flush();
        foreach ([$sales, $buyer] as $u) {
            $this->actingAs($u)->get('/reports/sales/stock')->assertOk();
            $this->actingAs($u)->get('/reports/sales/stock/download?'.http_build_query($this->range('D2', 'D3', ['format' => 'csv'])))->assertOk();
        }
        $this->actingAs($accounts)->get('/reports/sales/stock')->assertForbidden();
        $this->actingAs($accounts)->get('/reports/sales/stock/download?'.http_build_query($this->range('D2', 'D3', ['format' => 'csv'])))->assertForbidden();
        // Other Sales reports stay Sales only; "stock" no longer goes through the generic Sales download route name.
        $this->actingAs($buyer)->get('/reports/sales/sales/download?'.http_build_query($this->range('D2', 'D3', ['format' => 'csv'])))->assertForbidden();
        $this->assertSame(url('/reports/sales/stock/download'), route('reports.sales.stock-download'));
        $html = $this->actingAs($buyer)->get('/reports/sales/stock?in=purchase')->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('reports.sales.stock-download').'"', $html);
        $this->assertStringContainsString('<span class="badge b-neu">Purchase</span>', $html);
    }

    public function test_downloads_are_limited_to_ten_a_minute_including_the_shared_stock_report(): void
    {
        $buyer = $this->buyer();
        cache()->flush();
        $q = $this->range('D1', 'D3', ['format' => 'csv']);
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($buyer)->get('/reports/purchase/grn/download?'.http_build_query($q))->assertOk();
            $this->actingAs($buyer)->get('/reports/sales/stock/download?'.http_build_query($q))->assertOk();
        }
        $this->actingAs($buyer)->get('/reports/purchase/credit-debit/download?'.http_build_query($q))->assertStatus(429);
        $this->actingAs($buyer)->get('/reports/sales/stock/download?'.http_build_query($q))->assertStatus(429);
        $this->show($buyer, 'grn', $this->range('D1', 'D3'));   // Show is not limited
    }

    public function test_csv_cells_that_look_like_formulas_are_escaped(): void
    {
        $csv = $this->csv($this->admin(), 'po-summary', $this->range('D1', 'D3', ['status' => 'Cancelled']));
        $this->assertStringContainsString(',"\'=HYPERLINK(""http://x.example"",""Click"")",', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
        // Plain negative-looking numbers are not touched: quantities out stay numbers.
        $ledger = $this->csv($this->admin(), 'stock-ledger', $this->range('D2', 'D3', ['item' => $this->d['a']->id, 'unit' => $this->d['u1']->id]));
        $this->assertStringContainsString(',0.000,15.000,35.000', $ledger);
        // On screen the name is shown as text.
        $this->show($this->admin(), 'po-summary', $this->range('D1', 'D3', ['status' => 'Cancelled']))->assertSee('=HYPERLINK(&quot;http://x.example&quot;,&quot;Click&quot;)', false);
    }

    public function test_show_runs_a_fixed_number_of_queries_whatever_the_rows(): void
    {
        $admin = $this->admin();
        $count = function (string $report, array $q) use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->show($admin, $report, $q);
            // The layout's own queries (CRM alert counts cached for a while, roles, units of the user) are not counted.
            $n = collect(DB::getQueryLog())->pluck('query')
                ->reject(fn ($s) => preg_match('/"(enquiries|site_visits|quotations|customer_pos|module_settings|model_has_roles|unit_user|permissions|role_has_permissions)"/', $s))->count();
            DB::disableQueryLog();

            return $n;
        };
        // Warm up once (permission cache, currency codes), then count.
        foreach (['po-summary' => $this->range('D1', 'D3'), 'grn' => $this->range('D1', 'D3'), 'stock-transfers' => $this->range('D2', 'D3'),
            'stock-ledger' => $this->range('D2', 'D3', ['item' => $this->d['a']->id])] as $r => $q) {
            $count($r, $q);
        }
        $before = [
            'po-summary' => $count('po-summary', $this->range('D1', 'D3')),
            'grn' => $count('grn', $this->range('D1', 'D3')),
            'stock-transfers' => $count('stock-transfers', $this->range('D2', 'D3')),
            'stock-ledger' => $count('stock-ledger', $this->range('D2', 'D3', ['item' => $this->d['a']->id])),
        ];
        // More documents of the same kinds: the number of queries must not grow with the rows.
        $this->travelTo($this->days['D2']);
        $po = $this->po($this->buyer(), $this->d['s2'], [['item_id' => (string) $this->d['a']->id, 'quantity' => '5', 'rate' => ''], ['item_id' => (string) $this->d['b']->id, 'quantity' => '5', 'rate' => '']]);
        $this->po($this->buyer(), $this->d['s1'], [['item_id' => (string) $this->d['b']->id, 'quantity' => '9', 'rate' => '']]);
        $this->grn([$this->poLine($po, 1, 5), $this->poLine($po, 2, 5)], ['party_id' => $this->d['s2']->id, 'invoice_no' => 'KS-1', 'invoice_amount' => '1']);
        $this->transfer($this->d['b'], 2);
        $this->invoice(1);
        $this->travelBack();
        $this->assertSame($before, [
            'po-summary' => $count('po-summary', $this->range('D1', 'D3')),
            'grn' => $count('grn', $this->range('D1', 'D3')),
            'stock-transfers' => $count('stock-transfers', $this->range('D2', 'D3')),
            'stock-ledger' => $count('stock-ledger', $this->range('D2', 'D3', ['item' => $this->d['a']->id])),
        ]);
    }
}
