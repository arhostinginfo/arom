<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Currency;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\PageSetupService;
use App\Services\PurchaseOrderService;
use App\Support\WorkContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Purchase › Purchase order (PURCHASE tab, 27/10/2026). Test database only. */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

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
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function buyer(): User
    {
        return $this->user('rahul@company.example', 'Purchase');
    }

    private function today(): string
    {
        return today()->format('d/m/Y');
    }

    /** Company in Maharashtra; suppliers in Maharashtra, Karnataka and the USA; a customer; their items. */
    private function data(): array
    {
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        $inr = Currency::defaultId();
        $usd = Currency::where('code', 'USD')->first() ?? Currency::create(['country' => 'United States', 'code' => 'USD', 'symbol' => '$']);
        $mh = Party::create(['name' => 'Shree Ganesh Castings', 'gstin' => '27AAAPS1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Shiroli MIDC, Kolhapur', 'payment_terms' => '60 days', 'status' => 'Active', 'currency_id' => $inr]);
        $ka = Party::create(['name' => 'Kaveri Metals', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Both', 'billing_address' => 'Peenya, Bengaluru', 'status' => 'Active', 'currency_id' => $inr]);
        $us = Party::create(['name' => 'Ohio Tooling Inc', 'type' => 'Supplier', 'billing_address' => 'Ohio, USA', 'status' => 'Active', 'currency_id' => $usd->id]);
        $cust = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan', 'status' => 'Active', 'currency_id' => $inr]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '7325', 'type' => 'HSN', 'description' => 'Cast articles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $kg = Uom::where('code', 'KG')->first();
        $mk = fn (?Party $p, string $no, string $type, float $price, $tax, $uom, $cur = null) => Item::create(['party_id' => $p?->id, 'description' => 'Part '.$no, 'item_no' => $no,
            'type' => $type, 'hsn_sac_code_id' => $hsn->id, 'uom_id' => $uom->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'currency_id' => $cur ?? $inr, 'status' => 'Active']);

        return [
            'mh' => $mh, 'ka' => $ka, 'us' => $us, 'cust' => $cust, 'gst18' => $gst18, 'gst12' => $gst12, 'nos' => $nos, 'kg' => $kg,
            'casting' => $mk($mh, 'CST-4410', 'Purchase', 210, $gst18, $nos),
            'cover' => $mk($mh, 'CST-4412', 'Purchase', 145, $gst12, $nos),
            'sheet' => $mk(null, 'RM-CR-1.6', 'Both', 68, $gst18, $kg),
            'bracket' => $mk($cust, 'BRK-2210', 'Sales', 42.5, $gst18, $nos),
            'kaPart' => $mk($ka, 'KAV-100', 'Purchase', 100, $gst18, $nos),
            'tool' => $mk($us, 'OH-900', 'Purchase', 50, $gst18, $nos, $usd->id),
        ];
    }

    private function payload(Party $party, array $lines, array $over = []): array
    {
        return array_merge(['party_id' => $party->id, 'po_date' => $this->today(), 'delivery_date' => '', 'payment_terms' => '60 days', 'currency_id' => '',
            'transporter_id' => '', 'billing_address' => 'Plot 21, MIDC Chakan, Pune', 'shipping_address' => 'Plot 21, MIDC Chakan, Pune', 'remarks' => '',
            'lines_json' => json_encode($lines)], $over);
    }

    private function line(Item $item, $qty, $rate = '', array $over = []): array
    {
        return array_merge(['item_id' => (string) $item->id, 'description' => '', 'quantity' => (string) $qty, 'uom_id' => '', 'rate' => (string) $rate, 'discount_percent' => '', 'tax_structure_id' => '', 'delivery_date' => ''], $over);
    }

    private function makePo(array $d, array $lines, ?User $as = null, array $over = []): PurchaseOrder
    {
        $this->actingAs($as ?? $this->buyer())->post('/purchase/orders', $this->payload($over['party'] ?? $d['mh'], $lines, array_diff_key($over, ['party' => 1])))->assertSessionHasNoErrors();

        return PurchaseOrder::latest('id')->first();
    }

    private function approvedPo(array $d): PurchaseOrder
    {
        $po = $this->makePo($d, [$this->line($d['casting'], 1000), $this->line($d['cover'], 300, '145')]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/approve")->assertSessionHasNoErrors();

        return $po->fresh('lines');
    }

    public function test_menu_opens_the_built_page_instead_of_the_sample_screen(): void
    {
        $this->data();
        $buyer = $this->buyer();
        $this->actingAs($buyer)->get('/app/po')->assertRedirect(route('purchase.orders.index'));
        $html = $this->actingAs($buyer)->get('/purchase/orders')->assertOk()->assertSee('Purchase order')->assertSee('Show all headings')->assertSee('Save purchase order')->getContent();
        $this->assertStringContainsString(route('purchase.orders.index'), $html);
        $this->assertStringContainsString('btn add sm', $html); // green Add new / Add item
        $this->assertStringNotContainsString('class="sample"', $html);
        // a role without Purchase order rights: menu item hidden, page and old address refused
        $sales = $this->user('priya@company.example', 'Sales');
        $this->actingAs($sales)->get('/purchase/orders')->assertForbidden();
        $this->actingAs($sales)->get('/app/po')->assertForbidden();
        $this->assertStringNotContainsString(route('purchase.orders.index'), $this->actingAs($sales)->get('/dashboard')->getContent());
    }

    public function test_create_numbers_defaults_gst_totals_and_log(): void
    {
        $d = $this->data();
        $po = $this->makePo($d, [
            $this->line($d['casting'], 100),                                                  // rate, UOM, tax from the item: 100 x 210 at 18%
            $this->line($d['sheet'], '1,000', '70', ['discount_percent' => '10', 'tax_structure_id' => (string) $d['gst12']->id, 'uom_id' => (string) $d['nos']->id, 'description' => 'CR sheet cut']),
        ]);
        $year = WorkContext::financialYear();
        $this->assertSame('PO/'.$year->code.'/0001', $po->po_no);
        $this->assertSame(PurchaseOrder::DRAFT, $po->status);
        $this->assertSame('Intra-state', $po->supply_type);
        $this->assertSame(Currency::defaultId(), $po->currency_id);
        $l = $po->lines;
        $this->assertSame('210.0000', $l[0]->rate);
        $this->assertSame($d['nos']->id, $l[0]->uom_id);
        $this->assertSame($d['gst18']->id, $l[0]->tax_structure_id);
        $this->assertSame('Part CST-4410', $l[0]->description);
        $this->assertSame('7325', $l[0]->hsn_code);
        $this->assertSame('21000.00', $l[0]->taxable_value);
        $this->assertSame('1890.00', $l[0]->cgst_amount);
        $this->assertSame('63000.00', $l[1]->taxable_value);   // 1000 x 70 less 10%
        $this->assertSame('3780.00', $l[1]->sgst_amount);       // 6%
        $this->assertSame($d['nos']->id, $l[1]->uom_id);
        $this->assertSame('CR sheet cut', $l[1]->description);
        $this->assertSame('84000.00', $po->taxable_value);
        $this->assertSame('5670.00', $po->cgst_amount);
        $this->assertSame('5670.00', $po->sgst_amount);
        $this->assertSame('0.00', $po->igst_amount);
        $this->assertSame('95340.00', $po->grand_total);
        $this->assertSame('0.000', $l[0]->received_quantity);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.created')->where('subject_type', 'purchase_order')->where('subject_id', $po->id)->exists());

        // the second PO takes the next number; another state is IGST; an import supplier has no GST
        $ka = $this->makePo($d, [$this->line($d['kaPart'], 10)], null, ['party' => $d['ka']]);
        $this->assertSame('PO/'.$year->code.'/0002', $ka->po_no);
        $this->assertSame('Inter-state', $ka->supply_type);
        $this->assertSame('180.00', $ka->igst_amount);
        $this->assertSame('1180.00', $ka->grand_total);
        $us = $this->makePo($d, [$this->line($d['tool'], 10)], null, ['party' => $d['us']]);
        $this->assertSame('Import', $us->supply_type);
        $this->assertSame('USD', $us->currency->code);
        $this->assertSame('500.00', $us->grand_total);
        // blank rate of an item priced in INR on a USD PO: the rate must be typed
        $this->actingAs($this->buyer())->post('/purchase/orders', $this->payload($d['us'], [$this->line($d['sheet'], 5)]))->assertSessionHasErrors('lines.0.rate');
    }

    public function test_validation_supplier_items_dates_and_page_setup(): void
    {
        $d = $this->data();
        $b = $this->buyer();
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['cust'], [$this->line($d['casting'], 1)]))->assertSessionHasErrors('party_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['bracket'], 1)]))->assertSessionHasErrors('lines.0.item_id');   // a Sales item
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['kaPart'], 1)]))->assertSessionHasErrors('lines.0.item_id');    // another supplier's
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], []))->assertSessionHasErrors('lines');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 0)]))->assertSessionHasErrors('lines.0.quantity');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1, '', ['discount_percent' => '120'])]))->assertSessionHasErrors('lines.0.discount_percent');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)], ['po_date' => today()->addDay()->format('d/m/Y')]))->assertSessionHasErrors('po_date');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)], ['po_date' => today()->subYears(2)->format('d/m/Y')]))->assertSessionHasErrors('po_date');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)], ['delivery_date' => today()->subDay()->format('d/m/Y')]))->assertSessionHasErrors('delivery_date');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1, '', ['uom_id' => '999999'])]))->assertSessionHasErrors('lines.0.uom_id');
        $this->assertSame(0, PurchaseOrder::count());
        // the item of no party (Both) is allowed for any supplier
        $this->makePo($d, [$this->line($d['sheet'], 1)]);

        // Page setup: Remarks mandatory, Transporter hidden
        $fields = [];
        foreach (app(PageSetupService::class)->fields('purchase-order') as $f => $cur) {
            $fields[$f] = ['visible' => $f === 'transporter_id' ? '0' : '1', 'required' => $f === 'remarks' ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put('/admin/page-setup/purchase-order', ['fields' => $fields])->assertSessionHasNoErrors();
        app()->forgetInstance(PageSetupService::class);
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)]))->assertSessionHasErrors('remarks');
        $this->actingAs($b)->get('/purchase/orders/create')->assertOk()->assertDontSee('id="transporter_id"', false)->assertSee('id="remarks"', false);
    }

    public function test_approval_flow_and_edit_rules(): void
    {
        $d = $this->data();
        $rule = ApprovalRule::for('purchase_order');
        $this->assertTrue($rule->exists);
        $this->assertSame('100000.00', $rule->approve_above_amount);

        // at or below Rs 1,00,000: approved at once
        $small = $this->makePo($d, [$this->line($d['casting'], 10)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$small->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::APPROVED, $small->fresh()->status);
        $this->assertSame('Approved automatically', $small->approvals()->first()->action);

        // above: pending; the buyer cannot approve; reject needs a reason
        $big = $this->makePo($d, [$this->line($d['casting'], 1000)]);
        $this->actingAs($this->buyer())->get("/purchase/orders/{$big->id}")->assertOk()->assertSee('Send for approval');
        $this->actingAs($this->buyer())->post("/purchase/orders/{$big->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::PENDING, $big->fresh()->status);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$big->id}/approve")->assertForbidden();
        $this->actingAs($this->buyer())->put("/purchase/orders/{$big->id}", $this->payload($d['mh'], [$this->line($d['casting'], 900)]))->assertSessionHasErrors('form');
        $this->actingAs($this->admin())->post("/purchase/orders/{$big->id}/reject", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($this->admin())->post("/purchase/orders/{$big->id}/reject", ['remarks' => 'Rate too high'])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::REJECTED, $big->fresh()->status);
        $this->assertSame('Rate too high', $big->fresh()->status_reason);
        // a rejected PO that is changed is a Draft again, keeping its line id
        $lineId = $big->lines()->first()->id;
        $this->actingAs($this->buyer())->put("/purchase/orders/{$big->id}", $this->payload($d['mh'], [$this->line($d['casting'], 1000, '200', ['id' => (string) $lineId])]))->assertSessionHasNoErrors();
        $big->refresh();
        $this->assertSame(PurchaseOrder::DRAFT, $big->status);
        $this->assertSame($lineId, $big->lines()->first()->id);
        $this->assertSame('200000.00', $big->taxable_value);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$big->id}/submit");
        $this->actingAs($this->admin())->post("/purchase/orders/{$big->id}/approve")->assertSessionHasNoErrors();
        $big->refresh();
        $this->assertSame(PurchaseOrder::APPROVED, $big->status);
        $this->assertSame($this->admin()->id, $big->approved_by);
        // approved: only an Admin can change it
        $this->actingAs($this->buyer())->put("/purchase/orders/{$big->id}", $this->payload($d['mh'], [$this->line($d['casting'], 1000, '200', ['id' => (string) $lineId])], ['remarks' => 'x']))->assertSessionHasErrors('form');
        $this->actingAs($this->admin())->put("/purchase/orders/{$big->id}", $this->payload($d['mh'], [$this->line($d['casting'], 1000, '200', ['id' => (string) $lineId])], ['remarks' => 'Urgent']))->assertSessionHasNoErrors();
        $this->assertSame('Urgent', $big->fresh()->remarks);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.status_changed')->where('subject_id', $big->id)->exists());

        // the approver role: a role with Purchase order approval can approve
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Purchase head', 'guard_name' => 'web']);
        $role->givePermissionTo(['purchase-order-approval.view', 'purchase-order-approval.edit']);
        $head = $this->user('head@company.example', 'Purchase head');
        $third = $this->makePo($d, [$this->line($d['casting'], 600)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$third->id}/submit");
        $this->actingAs($head)->get("/purchase/orders/{$third->id}")->assertOk()->assertSee('Approve');
        $this->actingAs($head)->post("/purchase/orders/{$third->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::APPROVED, $third->fresh()->status);

        // Admin › Approval: the rule can be changed; a wrong amount shows on its own card
        $this->actingAs($this->admin())->get('/admin/approval')->assertOk()->assertSee('Save purchase order rule')->assertSee('Purchase head');
        $this->actingAs($this->admin())->put('/admin/approval/purchase-order', ['is_required' => '1', 'approve_above_amount' => 'abc', '_po' => '1'])->assertSessionHasErrorsIn('purchase_order', 'approve_above_amount');
        $this->actingAs($this->admin())->put('/admin/approval/purchase-order', ['is_required' => '1', 'approve_above_amount' => '', '_po' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(ApprovalRule::for('purchase_order')->approve_above_amount);
        $tiny = $this->makePo($d, [$this->line($d['casting'], 1)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$tiny->id}/submit");
        $this->assertSame(PurchaseOrder::PENDING, $tiny->fresh()->status);
    }

    public function test_grn_contract_receive_pending_and_locks(): void
    {
        $d = $this->data();
        $po = $this->approvedPo($d);
        [$a, $b] = [$po->lines[0], $po->lines[1]];
        $svc = app(PurchaseOrderService::class);
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertSame(1, PurchaseOrder::pendingReceipt()->count());

        $svc->receive($po, [$a->id => 400], $admin);
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        $this->assertSame(600.0, $a->fresh()->pending());
        try {
            $svc->receive($po, [$a->id => 601], $admin);
            $this->fail('Received more than pending');
        } catch (ValidationException) {
        }
        $this->assertSame('400.000', $a->fresh()->received_quantity);

        // a received line keeps its item and rate and cannot go below what was received; it cannot be removed
        $keep = fn ($qty, $rate = '210') => [$this->line($d['casting'], $qty, $rate, ['id' => (string) $a->id]), $this->line($d['cover'], 300, '145', ['id' => (string) $b->id])];
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], $keep(300)))->assertSessionHasErrors('lines.0.quantity');
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], $keep(1000, '200')))->assertSessionHasErrors('lines.0.rate');
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], [$this->line($d['cover'], 300, '145', ['id' => (string) $b->id])]))->assertSessionHasErrors('lines');
        // another supplier: its items are refused by the form (S-4), and the service refuses the party change too
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['ka'], $keep(1000)))->assertSessionHasErrors('lines.0.item_id');
        try {
            $svc->update($po, ['party_id' => $d['ka']->id, 'lines' => $keep(1000)], $admin);
            $this->fail('Supplier changed on a received PO');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('party_id', $e->errors());
        }
        $this->assertSame($d['mh']->id, $po->fresh()->party_id);
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], $keep(1200)))->assertSessionHasNoErrors();
        $this->assertSame('1200.000', $a->fresh()->quantity);
        $this->assertSame('400.000', $a->fresh()->received_quantity);
        // a higher value above the limit goes back for approval; approved again, it returns to Partly received
        $this->assertSame(PurchaseOrder::PENDING, $po->fresh()->status);
        $this->actingAs($admin)->post("/purchase/orders/{$po->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        // nothing can be deleted or cancelled once received
        $this->actingAs($admin)->delete("/purchase/orders/{$po->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->post("/purchase/orders/{$po->id}/cancel", ['remarks' => 'Not needed'])->assertSessionHasErrors('status');

        $svc->receive($po, [$a->id => 800, $b->id => 300], $admin);
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);
        $this->assertSame(0, PurchaseOrder::pendingReceipt()->count());
        // a GRN cancelled: its quantity goes back and the PO is open again
        $svc->receive($po, [$b->id => -300], $admin);
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        try {
            $svc->receive($po, [$b->id => -1], $admin);
            $this->fail('Took back more than received');
        } catch (ValidationException) {
        }
        // only an Approved (or Partly received) PO can receive
        $draft = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $this->expectException(ValidationException::class);
        $svc->receive($draft, [$draft->lines()->first()->id => 1], $admin);
    }

    public function test_delete_cancel_list_lookup_pdf_and_masters_in_use(): void
    {
        $d = $this->data();
        $b = $this->buyer();
        $draft = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $this->actingAs($b)->get("/purchase/orders/{$draft->id}")->assertOk()->assertSee('Delete purchase order')->assertDontSee('Cancel purchase order');
        $this->actingAs($b)->delete("/purchase/orders/{$draft->id}")->assertRedirect(route('purchase.orders.index'));
        $this->assertSoftDeleted('purchase_orders', ['id' => $draft->id]);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.deleted')->exists());

        $po = $this->approvedPo($d);
        $this->actingAs($b)->get("/purchase/orders/{$po->id}")->assertOk()->assertSee('Cancel purchase order')->assertDontSee('Delete purchase order');
        $this->actingAs($b)->delete("/purchase/orders/{$po->id}")->assertSessionHasErrors('delete');
        $this->actingAs($b)->post("/purchase/orders/{$po->id}/cancel", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($b)->post("/purchase/orders/{$po->id}/cancel", ['remarks' => 'Supplier cannot deliver'])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::CANCELLED, $po->fresh()->status);
        $this->assertSame(0, PurchaseOrder::pendingReceipt()->count());

        $open = $this->approvedPo($d);
        $pending = $this->makePo($d, [$this->line($d['casting'], 700)]);
        $this->actingAs($b)->post("/purchase/orders/{$pending->id}/submit");
        $count = fn ($view) => $this->actingAs($b)->get('/purchase/orders/list'.($view ? '?view='.$view : ''))->assertOk()->viewData('rows')->total();
        $this->assertSame(3, $count(null));
        $this->assertSame(1, $count('pending'));
        $this->assertSame(1, $count('approved'));
        $this->assertSame(1, $count('open'));
        $this->actingAs($b)->get('/purchase/orders/list?q=shree')->assertOk()->assertSee($open->po_no);
        $this->actingAs($b)->getJson('/purchase/orders/lookup?q='.urlencode(substr($open->po_no, 0, 6)))->assertOk()->assertJsonFragment(['id' => $open->id]);
        $this->actingAs($b)->getJson('/purchase/parties?q=shree')->assertOk()->assertJsonFragment(['label' => 'Shree Ganesh Castings'])->assertJsonMissing(['label' => 'Apex Auto Components']);
        $this->actingAs($b)->getJson("/purchase/parties/{$d['mh']->id}/info")->assertOk()->assertJson(['supply' => 'Intra-state', 'payment_terms' => '60 days', 'currency' => 'INR']);
        $this->actingAs($b)->getJson("/purchase/parties/{$d['cust']->id}/info")->assertNotFound();
        $items = $this->actingAs($b)->getJson("/purchase/items?party={$d['mh']->id}")->assertOk()->json();
        $this->assertEqualsCanonicalizing(['CST-4410', 'CST-4412', 'RM-CR-1.6'], array_map(fn ($i) => strtok($i['label'], ' '), $items));
        $this->assertSame((string) $d['nos']->id, $items[0]['data']['uom']);

        $res = $this->actingAs($b)->get("/purchase/orders/{$open->id}/pdf")->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));

        // masters used on a PO cannot be deleted
        $this->actingAs($this->admin())->delete("/admin/masters/lists/uoms/{$d['nos']->id}")->assertSessionHasErrors('delete');
        $this->actingAs($this->admin())->delete("/admin/masters/items/{$d['cover']->id}")->assertSessionHasErrors('delete');
    }

    // ---- Tester's review fixes (27/10/2026) ----

    public function test_review_fix_supplier_change_cannot_keep_the_old_suppliers_items(): void
    {
        $d = $this->data();
        $po = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $lineId = (string) $po->lines()->first()->id;
        // Kaveri (Both) cannot take Shree Ganesh's casting, even though it is already on the PO
        $this->actingAs($this->buyer())->put("/purchase/orders/{$po->id}", $this->payload($d['ka'], [$this->line($d['casting'], 5, '', ['id' => $lineId])]))->assertSessionHasErrors('lines.0.item_id');
        $this->assertSame($d['mh']->id, $po->fresh()->party_id);
        // the same supplier keeps an item made inactive since
        $d['casting']->update(['status' => 'Inactive']);
        $this->actingAs($this->buyer())->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], [$this->line($d['casting'], 6, '', ['id' => $lineId])]))->assertSessionHasNoErrors();
        // switching supplier with that supplier's own items is fine
        $this->actingAs($this->buyer())->put("/purchase/orders/{$po->id}", $this->payload($d['ka'], [$this->line($d['kaPart'], 2)]))->assertSessionHasNoErrors();
        $this->assertSame('Inter-state', $po->fresh()->supply_type);
    }

    public function test_review_fix_admin_cannot_delete_a_sent_or_approved_po_and_cancel_is_for_sent_ones(): void
    {
        $d = $this->data();
        $admin = $this->admin();
        $pending = $this->makePo($d, [$this->line($d['casting'], 1000)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$pending->id}/submit");
        $this->actingAs($admin)->delete("/purchase/orders/{$pending->id}")->assertSessionHasErrors('delete');
        $approved = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$approved->id}/submit");
        $this->assertSame(PurchaseOrder::APPROVED, $approved->fresh()->status);
        $this->actingAs($admin)->delete("/purchase/orders/{$approved->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $pending->id]);
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $approved->id]);
        // a Draft is deleted, not cancelled
        $draft = $this->makePo($d, [$this->line($d['casting'], 1)]);
        $this->actingAs($admin)->post("/purchase/orders/{$draft->id}/cancel", ['remarks' => 'No'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->post("/purchase/orders/{$pending->id}/cancel", ['remarks' => 'No'])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::CANCELLED, $pending->fresh()->status);
    }

    public function test_review_fix_admin_edit_recomputes_receipt_status_and_reapproval(): void
    {
        $d = $this->data();
        $admin = $this->admin();
        $svc = app(PurchaseOrderService::class);
        $po = $this->makePo($d, [$this->line($d['casting'], 10), $this->line($d['cover'], 5)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/submit");
        $po = $po->fresh('lines');
        [$a, $b] = [$po->lines[0], $po->lines[1]];
        $this->assertSame(PurchaseOrder::APPROVED, $po->status);
        $this->actingAs($admin);
        $svc->receive($po, [$a->id => 4, $b->id => 5], $admin);
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        // lowering the open line to what was received: the PO is Received
        $lines = fn ($qa) => [$this->line($d['casting'], $qa, '', ['id' => (string) $a->id]), $this->line($d['cover'], 5, '', ['id' => (string) $b->id])];
        $this->actingAs($admin)->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], $lines(4)))->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);

        // an Approved PO raised by an Admin but still within the limit stays Approved
        $ok = $this->makePo($d, [$this->line($d['casting'], 10)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$ok->id}/submit");
        $okLine = (string) $ok->lines()->first()->id;
        $this->actingAs($admin)->put("/purchase/orders/{$ok->id}", $this->payload($d['mh'], [$this->line($d['casting'], 100, '', ['id' => $okLine])]))->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::APPROVED, $ok->fresh()->status);
        // raised above the limit: back to Pending approval, logged, approval cleared
        $this->actingAs($admin)->put("/purchase/orders/{$ok->id}", $this->payload($d['mh'], [$this->line($d['casting'], 1000, '', ['id' => $okLine])]))->assertSessionHasNoErrors();
        $ok->refresh();
        $this->assertSame(PurchaseOrder::PENDING, $ok->status);
        $this->assertNull($ok->approved_at);
        $this->assertSame('Submitted', $ok->approvals()->latest('id')->first()->action);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.status_changed')->where('subject_id', $ok->id)->where('description', 'like', '%back for approval%')->exists());
        // lowered again (no new approval needed for a lower value) after approval: stays Approved
        $this->actingAs($admin)->post("/purchase/orders/{$ok->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/purchase/orders/{$ok->id}", $this->payload($d['mh'], [$this->line($d['casting'], 900, '', ['id' => $okLine])]))->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrder::APPROVED, $ok->fresh()->status);
    }

    public function test_review_fix_currency_follows_the_supplier(): void
    {
        $d = $this->data();
        $usd = Currency::where('code', 'USD')->first();
        $b = $this->buyer();
        // a currency other than the supplier's is refused, both ways
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)], ['currency_id' => (string) $usd->id]))->assertSessionHasErrors('currency_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['us'], [$this->line($d['tool'], 1)], ['currency_id' => (string) Currency::defaultId()]))->assertSessionHasErrors('currency_id');
        // the supplier's own currency (or blank) is fine
        $po = $this->makePo($d, [$this->line($d['tool'], 1)], null, ['party' => $d['us'], 'currency_id' => (string) $usd->id]);
        $this->assertSame($usd->id, $po->currency_id);
        // the service forces the supplier's currency even when called directly with INR: Import, no GST, always approval
        $this->actingAs($b);
        $direct = app(PurchaseOrderService::class)->create(['party_id' => $d['us']->id, 'po_date' => $this->today(), 'currency_id' => Currency::defaultId(),
            'lines' => [$this->line($d['tool'], 1)]], $b, Unit::first(), WorkContext::financialYear());
        $this->assertSame($usd->id, $direct->currency_id);
        $this->assertSame('Import', $direct->supply_type);
        $this->assertSame('0.00', $direct->igst_amount);
        app(PurchaseOrderService::class)->submit($direct, $b);
        $this->assertSame(PurchaseOrder::PENDING, $direct->fresh()->status);
        // an approval-only user reaches the page from the old address
        $role = \Spatie\Permission\Models\Role::create(['name' => 'PO approver', 'guard_name' => 'web']);
        $role->givePermissionTo(['purchase-order-approval.view', 'purchase-order-approval.edit']);
        $this->actingAs($this->user('appr@company.example', 'PO approver'))->get('/app/po')->assertRedirect(route('purchase.orders.index'));
    }
}
