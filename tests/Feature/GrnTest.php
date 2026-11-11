<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Grn;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\PageSetupService;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Purchase › GRN entry (27/10/2026): match, GRN no, PO receive, quality and stock, cancel, screens. Test database only. */
class GrnTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private array $d;

    protected function setUp(): void
    {
        parent::setUp();
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        $inr = Currency::defaultId();
        $mh = Party::create(['name' => 'Shree Ganesh Castings', 'gstin' => '27AAAPS1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Kolhapur', 'status' => 'Active', 'currency_id' => $inr]);
        $ka = Party::create(['name' => 'Kaveri Metals', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Supplier', 'billing_address' => 'Bengaluru', 'status' => 'Active', 'currency_id' => $inr]);
        $cust = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan', 'status' => 'Active', 'currency_id' => $inr]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '7325', 'type' => 'HSN', 'description' => 'Cast articles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn (?Party $p, string $no, float $price, $tax) => Item::create(['party_id' => $p?->id, 'description' => 'Part '.$no, 'item_no' => $no, 'type' => 'Purchase',
            'hsn_sac_code_id' => $hsn->id, 'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'currency_id' => $inr, 'status' => 'Active']);
        $this->d = ['mh' => $mh, 'ka' => $ka, 'cust' => $cust, 'casting' => $mk($mh, 'CST-4410', 210, $gst18), 'cover' => $mk($mh, 'CST-4412', 145, $gst12),
            'kaPart' => $mk($ka, 'KAV-100', 100, $gst18), 'unit' => Unit::orderBy('id')->first()];
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98224'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([$this->d['unit']->id]);

        return $u;
    }

    private function buyer(): User
    {
        return $this->user('rahul@company.example', 'Purchase');
    }

    /** Approved PO: casting 1000 @ 210 (GST 18%), cover 300 @ 145 (GST 12%). */
    private function approvedPo(Party $party = null, array $lines = null): PurchaseOrder
    {
        $party ??= $this->d['mh'];
        $lines ??= [['item_id' => (string) $this->d['casting']->id, 'quantity' => '1000', 'rate' => ''], ['item_id' => (string) $this->d['cover']->id, 'quantity' => '300', 'rate' => '145']];
        $this->actingAs($this->buyer())->post('/purchase/orders', ['party_id' => $party->id, 'po_date' => today()->format('d/m/Y'), 'billing_address' => 'Pune', 'shipping_address' => 'Pune',
            'lines_json' => json_encode($lines)])->assertSessionHasNoErrors();
        $po = PurchaseOrder::latest('id')->first();
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/submit")->assertSessionHasNoErrors();
        if ($po->fresh()->status === PurchaseOrder::PENDING) {
            $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/approve")->assertSessionHasNoErrors();
        }

        return $po->fresh('lines');
    }

    private function payload(array $lines, array $over = []): array
    {
        return array_merge(['invoice_type' => 'Purchase', 'party_id' => $this->d['mh']->id, 'invoice_no' => 'SGC/2026/1187', 'invoice_date' => today()->format('d/m/Y'),
            'invoice_amount' => '172620.00', 'transporter_id' => '', 'vehicle_no' => 'mh 09 cd 4410', 'eway_bill_no' => '281009876543', 'eway_bill_date' => today()->format('d/m/Y'),
            'customer_id' => '', 'sales_order_id' => '', 'remarks' => '', 'intent' => 'pending', 'lines_json' => json_encode($lines)], $over);
    }

    private function line(PurchaseOrder $po, int $n, $qty, $rate = ''): array
    {
        $l = $po->lines->firstWhere('line_no', $n);

        return ['purchase_order_id' => (string) $po->id, 'purchase_order_line_id' => (string) $l->id, 'quantity' => (string) $qty, 'rate' => (string) $rate];
    }

    private function balance(Item $item): float
    {
        return (float) DB::table('stock_balances')->where('unit_id', $this->d['unit']->id)->where('item_id', $item->id)->value('quantity');
    }

    /** casting 500 @ 210 + 18% = 1,23,900; cover 300 @ 145 + 12% = 48,720; total 1,72,620. */
    private function matchedGrn(PurchaseOrder $po, ?User $as = null, array $over = []): Grn
    {
        $this->actingAs($as ?? $this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)], $over + ['intent' => 'match']))
            ->assertSessionHasNoErrors();

        return Grn::latest('id')->first();
    }

    public function test_menu_opens_the_built_page_and_page_setup_lists_grn(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->get('/app/grn')->assertRedirect(route('purchase.grns.index'));
        $html = $this->actingAs($buyer)->get('/purchase/grns')->assertOk()->getContent();
        foreach (['GRN entry', 'Show all headings', 'Save and generate GRN no', 'Save as pending match', 'Generated when the amount matches', 'Stock is usable only after Approved', 'GRN list'] as $t) {
            $this->assertStringContainsString($t, $html, $t);
        }
        // No Import, CSV or PDF (prototype CAP grn: setup only).
        $this->assertStringNotContainsString('Generate PDF', $html);
        $this->assertStringNotContainsString('Download CSV', $html);
        $this->assertStringNotContainsString('>Import', $html);
        $this->actingAs($buyer)->get('/purchase/grns/list')->assertOk()->assertSee('Pending match')->assertSee('Pending quality')->assertSee('Approved');
        $this->actingAs($this->admin())->get('/admin/page-setup/grn')->assertOk()->assertSee('Quality approval')->assertSee('E-way bill no');
        $this->assertSame(['party_id', 'invoice_type', 'invoice_no', 'invoice_date', 'invoice_amount'],
            array_keys(array_filter(app(PageSetupService::class)->fields('grn'), fn ($f) => $f['required'])));
        // A Sales user has no GRN right.
        $this->actingAs($this->user('priya@company.example', 'Sales'))->get('/purchase/grns')->assertForbidden();
    }

    public function test_save_checks_mandatory_fields_po_lines_and_pending_quantity(): void
    {
        $po = $this->approvedPo();
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([], ['party_id' => '', 'invoice_no' => '', 'invoice_date' => '', 'invoice_amount' => '']))
            ->assertSessionHasErrors(['party_id', 'invoice_no', 'invoice_date', 'invoice_amount', 'lines']);
        // A customer cannot be the party.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)], ['party_id' => $this->d['cust']->id]))->assertSessionHasErrors('party_id');
        // Every Purchase invoice line needs a PO line (lines_without_po is off).
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([['item_id' => (string) $this->d['casting']->id, 'quantity' => '10', 'rate' => '']]))
            ->assertSessionHasErrors('lines.0.purchase_order_line_id');
        // Quantity cannot exceed the PO quantity pending.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 1001)]))->assertSessionHasErrors('lines.0.quantity');
        $this->assertStringContainsString('cannot exceed the PO quantity pending', session('errors')->first('lines.0.quantity'));
        // Another supplier's PO is refused.
        $kaPo = $this->approvedPo($this->d['ka'], [['item_id' => (string) $this->d['kaPart']->id, 'quantity' => '10', 'rate' => '']]);
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($kaPo, 1, 5)]))->assertSessionHasErrors('lines.0.purchase_order_line_id');
        // A draft PO is not open for receipt.
        $this->actingAs($buyer)->post('/purchase/orders', ['party_id' => $this->d['mh']->id, 'po_date' => today()->format('d/m/Y'),
            'lines_json' => json_encode([['item_id' => (string) $this->d['casting']->id, 'quantity' => '5', 'rate' => '']])]);
        $draft = PurchaseOrder::latest('id')->first();
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($draft->load('lines'), 1, 5)]))->assertSessionHasErrors('lines.0.purchase_order_line_id');
        // SO no must be the customer's.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 5)], ['sales_order_id' => '999']))->assertSessionHasErrors('sales_order_id');
        $this->assertSame(0, Grn::count());
    }

    public function test_mismatch_saves_as_pending_match_and_a_later_edit_matches_it_and_receives_the_po(): void
    {
        $po = $this->approvedPo();
        $buyer = $this->buyer();
        // "Save and generate GRN no" with a mismatch is refused and saves nothing.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 500)], ['intent' => 'match']))
            ->assertSessionHasErrors('invoice_amount');
        $this->assertStringStartsWith('Amount not matched. Fix it or save as pending match.', session('errors')->first('invoice_amount'));
        $this->assertSame(0, Grn::count());

        // "Save as pending match": saved, no number, PO untouched, no stock.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 500)]))->assertSessionHasNoErrors()
            ->assertSessionHas('toast', fn ($t) => str_contains($t, 'pending match'));
        $grn = Grn::latest('id')->first();
        $this->assertNull($grn->grn_no);
        $this->assertSame(Grn::PENDING_MATCH, $grn->match_status);
        $this->assertSame('123900.00', (string) $grn->grand_total);
        $this->assertSame('MH 09 CD 4410', $grn->vehicle_no);
        $this->assertSame('0.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
        $this->actingAs($buyer)->get('/purchase/grns/list?view=match')->assertOk()->assertSee('SGC/2026/1187');
        $this->assertSame(1, Grn::pendingMatch()->count());

        // Edit: add the cover line; the total now matches (within Rs 1) → GRN no, PO received quantities.
        $this->actingAs($buyer)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)], ['invoice_amount' => '172620.80']))
            ->assertSessionHasNoErrors();
        $grn->refresh();
        $this->assertSame(Grn::MATCHED, $grn->match_status);
        $this->assertMatchesRegularExpression('#^GRN/\d{2}-\d{2}/0001$#', $grn->grn_no);
        $this->assertSame(today()->format('Y-m-d'), $grn->grn_date->format('Y-m-d'));
        $po->refresh();
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->status);
        $this->assertSame('500.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
        $this->assertSame('300.000', (string) $po->lines()->where('line_no', 2)->value('received_quantity'));
        // Stock waits for quality approval.
        $this->assertSame(0.0, $this->balance($this->d['casting']));
        $this->assertSame(1, Grn::pendingQuality()->count());
        $this->assertTrue(ActivityLog::where('action', 'grn.matched')->where('subject_id', $grn->id)->exists());
        $this->actingAs($buyer)->get("/purchase/grns/{$grn->id}")->assertOk()->assertSee($grn->grn_no)->assertSee('Waiting for quality approval');
        // The quality dialog's Result is a drop-down, Approved by default (radio to select, 02/11/2026); once matched the
        // invoice type is read-only text with a hidden field, not a drop-down.
        $qa = $this->actingAs($this->admin())->get("/purchase/grns/{$grn->id}")->assertOk();
        $this->assertMatchesRegularExpression('/<select class="inp\s*" id="qa_result" name="result" aria-required="true">\s*<option value="Approved" selected>Approved<\/option><option value="Rejected" >Rejected<\/option>\s*<\/select>/', $qa->getContent());
        $this->assertStringNotContainsString('id="invoice_type"', $qa->getContent());
        $qa->assertSee('<input type="hidden" name="invoice_type" value="Purchase">', false);

        // A matched GRN must stay matched; changing a quantity moves the PO by the difference.
        $this->actingAs($buyer)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 400), $this->line($po, 2, 300)], ['invoice_amount' => '172620.80']))
            ->assertSessionHasErrors('invoice_amount');
        $this->actingAs($buyer)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 400), $this->line($po, 2, 300)], ['invoice_amount' => '147840']))
            ->assertSessionHasNoErrors();
        $this->assertSame('400.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
        $this->assertSame($grn->grn_no, $grn->fresh()->grn_no);
        // Supplier fixed once matched.
        $this->actingAs($buyer)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 400)], ['party_id' => $this->d['ka']->id]))->assertSessionHasErrors();
        // A matched GRN cannot be deleted, only cancelled.
        $this->actingAs($this->admin())->delete("/purchase/grns/{$grn->id}")->assertSessionHasErrors('delete');
    }

    public function test_quality_approval_brings_accepted_stock_in_and_gives_rejected_back_to_the_po(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $this->assertNotNull($grn->grn_no);
        // The Purchase role has no GRN quality right.
        $this->actingAs($this->buyer())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertForbidden();
        // Rejecting needs a reason; a rejected quantity above the line is refused.
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Rejected'])->assertSessionHasErrors('remarks');
        $castLine = $grn->lines()->where('line_no', 1)->first();
        $coverLine = $grn->lines()->where('line_no', 2)->first();
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved', 'rejected' => [$castLine->id => '501']])
            ->assertSessionHasErrors('rejected.'.$castLine->id);
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());

        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved', 'remarks' => 'Checked', 'rejected' => [$castLine->id => '20']])
            ->assertSessionHasNoErrors();
        $grn->refresh();
        $this->assertSame(Grn::QA_APPROVED, $grn->quality_status);
        $this->assertTrue($grn->stock_posted);
        $this->assertSame(480.0, $this->balance($this->d['casting']));
        $this->assertSame(300.0, $this->balance($this->d['cover']));
        $this->assertSame('480.000', (string) $castLine->fresh()->accepted_quantity);
        $this->assertSame('20.000', (string) $castLine->fresh()->rejected_quantity);
        $this->assertSame('300.000', (string) $coverLine->fresh()->accepted_quantity);
        $this->assertSame(2, StockMovement::where('source_type', 'grn')->where('source_id', $grn->id)->count());
        // Rejected 20 are pending on the PO again.
        $this->assertSame('480.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
        $this->assertSame(1, Grn::qualityApproved()->count());
        // Final: no second decision, and the GRN can no longer be edited.
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Rejected', 'remarks' => 'x'])->assertSessionHasErrors('quality');
        $this->actingAs($this->admin())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)]))->assertSessionHasErrors('form');
        $this->actingAs($this->admin())->get("/purchase/grns/{$grn->id}")->assertOk()->assertSee('Accepted')->assertSee('Rejected');
    }

    public function test_quality_rejected_moves_no_stock_and_returns_the_quantity_to_the_po(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Rejected', 'remarks' => 'Blow holes'])->assertSessionHasNoErrors();
        $grn->refresh();
        $this->assertSame(Grn::QA_REJECTED, $grn->quality_status);
        $this->assertFalse($grn->stock_posted);
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());
        $this->assertSame(PurchaseOrder::APPROVED, $po->fresh()->status);
        $this->assertSame('0.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
    }

    public function test_cancel_reverses_po_and_stock_only_while_the_stock_is_still_held(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame(500.0, $this->balance($this->d['casting']));

        // Same supplier invoice no cannot be entered twice while the GRN is live.
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po->fresh('lines'), 1, 10)]))->assertSessionHasErrors('invoice_no');

        // A Purchase user (GRN · Delete, no quality right) cannot cancel a GRN whose stock came in.
        $this->actingAs($this->buyer())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'wrong'])->assertSessionHasErrors('status');

        // Some of the stock is used: cancel refused, nothing changes.
        DB::transaction(fn () => StockLedger::move($this->d['unit']->id, $this->d['casting']->id, today()->format('Y-m-d'), 0, 450, 'test_issue', 1));
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'Wrong supplier'])->assertSessionHasErrors('status');
        $this->assertStringContainsString('cannot be cancelled', session('errors')->first('status'));
        $this->assertSame(Grn::SAVED, $grn->fresh()->status);
        $this->assertSame('500.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));

        // Stock back: cancel takes the PO quantities and the stock back; the number stays; the invoice can be entered again.
        DB::transaction(fn () => StockLedger::remove('test_issue', 1));
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'Wrong supplier'])->assertSessionHasNoErrors();
        $grn->refresh();
        $this->assertSame(Grn::CANCELLED, $grn->status);
        $this->assertNotNull($grn->grn_no);
        $this->assertFalse($grn->stock_posted);
        $this->assertSame(0.0, $this->balance($this->d['casting']));
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());
        $this->assertSame(PurchaseOrder::APPROVED, $po->fresh()->status);
        $this->assertSame('0.000', (string) $po->lines()->where('line_no', 2)->value('received_quantity'));
        $this->assertTrue(ActivityLog::where('action', 'grn.cancelled')->where('subject_id', $grn->id)->exists());
        $again = $this->matchedGrn($po->fresh('lines'));
        $this->assertStringEndsWith('/0002', $again->grn_no);
    }

    public function test_pending_grn_can_be_deleted_and_credit_note_moves_no_po_or_stock(): void
    {
        $po = $this->approvedPo();
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)]))->assertSessionHasNoErrors();
        $pending = Grn::latest('id')->first();
        $this->actingAs($this->buyer())->post("/purchase/grns/{$pending->id}/cancel", ['remarks' => 'x'])->assertSessionHasErrors('status');
        $this->actingAs($this->buyer())->delete("/purchase/grns/{$pending->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('grns', ['id' => $pending->id]);

        // Supplier credit note: items without a PO, matched → number, quality approved at once, no stock, PO untouched.
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 5)], ['invoice_type' => 'Credit', 'invoice_no' => 'CN-12', 'invoice_amount' => '1239']))
            ->assertSessionHasErrors('lines.0.purchase_order_line_id');
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([['item_id' => (string) $this->d['casting']->id, 'quantity' => '5', 'rate' => '210']],
            ['invoice_type' => 'Credit', 'invoice_no' => 'CN-12', 'invoice_amount' => '1239', 'intent' => 'match']))->assertSessionHasNoErrors();
        $cn = Grn::latest('id')->first();
        $this->assertSame(Grn::MATCHED, $cn->match_status);
        $this->assertSame(Grn::QA_APPROVED, $cn->quality_status);
        $this->assertFalse($cn->stock_posted);
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());
        $this->assertSame('0.000', (string) $po->lines()->where('line_no', 1)->value('received_quantity'));
    }

    public function test_quality_hidden_in_page_setup_approves_at_match_and_customer_so_is_kept(): void
    {
        app(PageSetupService::class)->save('grn', collect(app(PageSetupService::class)->fields('grn'))
            ->map(fn ($f, $k) => ['visible' => $k !== 'quality_status', 'required' => $f['required']])->all(), $this->admin());
        $so = (new SalesOrder())->forceFill(['party_id' => $this->d['cust']->id, 'po_number' => 'APX-PO-77', 'start_date' => today(), 'status' => 'Approved',
            'unit_id' => $this->d['unit']->id, 'financial_year_id' => \App\Support\WorkContext::financialYear()->id, 'so_no' => 'SO/TEST/0001']);
        $so->save();
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po, null, ['customer_id' => $this->d['cust']->id, 'sales_order_id' => $so->id]);
        $this->assertSame(Grn::QA_APPROVED, $grn->quality_status);
        $this->assertTrue($grn->stock_posted);
        $this->assertSame(500.0, $this->balance($this->d['casting']));
        $this->assertSame($so->id, $grn->sales_order_id);
        $this->assertSame($this->d['cust']->id, $grn->customer_id);
    }

    public function test_open_po_picker_lists_pending_lines_of_the_supplier_only(): void
    {
        $po = $this->approvedPo();
        $this->approvedPo($this->d['ka'], [['item_id' => (string) $this->d['kaPart']->id, 'quantity' => '10', 'rate' => '']]);
        $json = $this->actingAs($this->buyer())->getJson('/purchase/grns/orders?party='.$this->d['mh']->id)->assertOk()->json();
        $this->assertCount(1, $json);
        $this->assertSame($po->po_no, $json[0]['no']);
        $this->assertSame(1000, (int) $json[0]['lines'][0]['pending']);
        $this->assertEquals(210, $json[0]['lines'][0]['rate']);
        $this->matchedGrn($po);
        $json = $this->actingAs($this->buyer())->getJson('/purchase/grns/orders?party='.$this->d['mh']->id)->json();
        // Cover fully received: only the casting line is left, with 500 pending.
        $this->assertCount(1, $json[0]['lines']);
        $this->assertEquals(500, $json[0]['lines'][0]['pending']);
    }

    /** PO edit payload keeping both lines (by id), with overrides per line. */
    private function poEdit(PurchaseOrder $po, array $lines): array
    {
        return ['party_id' => $this->d['mh']->id, 'po_date' => today()->format('d/m/Y'), 'billing_address' => 'Pune', 'shipping_address' => 'Pune', 'lines_json' => json_encode($lines)];
    }

    public function test_round1_po_line_on_a_grn_cannot_be_removed_or_change_item(): void
    {
        $po = $this->approvedPo();
        [$a, $b] = [$po->lines[0], $po->lines[1]];
        // A pending-match GRN on the cover line: nothing received yet.
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 2, 10)]))->assertSessionHasNoErrors();
        $grn = Grn::latest('id')->first();
        $keepA = ['id' => (string) $a->id, 'item_id' => (string) $this->d['casting']->id, 'quantity' => '1000', 'rate' => '210'];
        // Removing the cover line: a validation error, not a database error.
        $this->actingAs($this->admin())->put("/purchase/orders/{$po->id}", $this->poEdit($po, [$keepA]))->assertSessionHasErrors('lines');
        $this->assertStringContainsString('is on GRN', session('errors')->first('lines'));
        $this->assertSame(2, $po->lines()->count());
        // Changing the cover line's item while a live GRN points at it.
        $this->actingAs($this->admin())->put("/purchase/orders/{$po->id}", $this->poEdit($po, [$keepA,
            ['id' => (string) $b->id, 'item_id' => (string) $this->d['casting']->id, 'quantity' => '300', 'rate' => '145']]))->assertSessionHasErrors('lines.1.item_id');
        // Still refused once the GRN is deleted (its lines are kept as a record and point at the PO line).
        $this->actingAs($this->buyer())->delete("/purchase/grns/{$grn->id}")->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->put("/purchase/orders/{$po->id}", $this->poEdit($po, [$keepA]))->assertSessionHasErrors('lines');
        $this->assertSame(2, $po->lines()->count());
    }

    public function test_round1_supplier_items_endpoint_lists_the_suppliers_purchase_items(): void
    {
        $json = $this->actingAs($this->buyer())->getJson('/purchase/grns/supplier-items?party='.$this->d['mh']->id)->assertOk()->json();
        $ids = array_column($json, 'id');
        $this->assertContains($this->d['casting']->id, $ids);
        $this->assertNotContains($this->d['kaPart']->id, $ids);
        $cast = collect($json)->firstWhere('id', $this->d['casting']->id);
        $this->assertEquals(210, $cast['rate']);
        $this->assertSame('NOS', $cast['uom']);
        $this->assertEquals(9, $cast['c']);
        $this->assertEquals(18, $cast['i']);
        $this->assertSame([], $this->actingAs($this->buyer())->getJson('/purchase/grns/supplier-items')->json());
        $this->actingAs($this->user('priya@company.example', 'Sales'))->getJson('/purchase/grns/supplier-items?party='.$this->d['mh']->id)->assertForbidden();
        $html = $this->actingAs($this->buyer())->get('/purchase/grns/create')->getContent();
        $this->assertStringContainsString('data-supplier-items-url=', $html);
    }

    public function test_round1_another_units_user_gets_403_before_validation(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $u2 = Unit::orderBy('id')->skip(1)->first();
        $far = User::create(['name' => 'Far', 'email' => 'far@company.example', 'mobile' => '9822411111', 'password' => 'Secret2026', 'status' => 'Active']);
        $far->assignRole('Purchase');
        $far->givePermissionTo('grn-quality.edit');
        $far->units()->sync([$u2->id]);
        // Even an empty (invalid) payload is refused with 403, not validation messages naming the other unit's PO.
        $this->actingAs($far)->put("/purchase/grns/{$grn->id}", [])->assertForbidden();
        $this->actingAs($far)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 9999)]))->assertForbidden();
        $this->actingAs($far)->post("/purchase/grns/{$grn->id}/quality", [])->assertForbidden();
        $this->actingAs($far)->post("/purchase/grns/{$grn->id}/cancel", [])->assertForbidden();
        $this->assertSame(Grn::QA_PENDING, $grn->fresh()->quality_status);
    }

    public function test_round1_cancel_checks_the_quality_right_under_lock(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $stale = Grn::find($grn->id);              // read while quality is still Pending
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $buyer = $this->buyer();
        $this->actingAs($buyer);
        try {
            app(\App\Services\GrnService::class)->cancel($stale, $buyer, 'race');
            $this->fail('A user without GRN quality approval · Edit cancelled a quality-approved GRN.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('only a quality approver', $e->errors()['status'][0]);
        }
        $this->assertSame(Grn::SAVED, $grn->fresh()->status);
        $this->assertSame(500.0, $this->balance($this->d['casting']));
    }

    public function test_round1_invoice_date_cannot_be_after_the_grns_financial_year(): void
    {
        $po = $this->approvedPo();
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)]))->assertSessionHasNoErrors();
        $grn = Grn::latest('id')->first();
        // The GRN belongs to last year; today is after that year's end.
        $past = \App\Models\FinancialYear::forDate(now()->subYear());
        $grn->forceFill(['financial_year_id' => $past->id])->saveQuietly();
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 10)]))->assertSessionHasErrors('invoice_date');
        $this->assertStringContainsString($past->ends_on->format('d/m/Y'), session('errors')->first('invoice_date'));
        // A date inside that year is accepted.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 10)], ['invoice_date' => $past->ends_on->format('d/m/Y')]))
            ->assertSessionHasNoErrors();
    }
}
