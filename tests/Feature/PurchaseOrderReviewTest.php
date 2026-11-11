<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
use App\Services\PurchaseOrderService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Review of Purchase › Purchase order (27/10/2026): supplier / item rules, server totals, approval, receive(), delete, PDF, list. Test database only. */
class PurchaseOrderReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role, ?Unit $unit = null): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98225'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([($unit ?? Unit::first())->id]);

        return $u;
    }

    private function buyer(): User
    {
        return $this->user('buyer@company.example', 'Purchase');
    }

    private function today(): string
    {
        return today()->format('d/m/Y');
    }

    private function data(): array
    {
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        $inr = Currency::defaultId();
        $usd = Currency::where('code', 'USD')->first() ?? Currency::create(['country' => 'United States', 'code' => 'USD', 'symbol' => '$']);
        $mh = Party::create(['name' => 'Shree Ganesh Castings', 'gstin' => '27AAAPS1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Kolhapur', 'payment_terms' => '60 days', 'status' => 'Active', 'currency_id' => $inr]);
        $ka = Party::create(['name' => 'Kaveri Metals', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Both', 'billing_address' => 'Bengaluru', 'status' => 'Active', 'currency_id' => $inr]);
        $us = Party::create(['name' => 'Ohio Tooling Inc', 'type' => 'Supplier', 'billing_address' => 'Ohio, USA', 'status' => 'Active', 'currency_id' => $usd->id]);
        $cust = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan', 'status' => 'Active', 'currency_id' => $inr]);
        $inactive = Party::create(['name' => 'Old Supplier', 'gstin' => '27AAAPO1234K1Z5', 'type' => 'Supplier', 'billing_address' => 'Pune', 'status' => 'Inactive', 'currency_id' => $inr]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '7325', 'type' => 'HSN', 'description' => 'Cast articles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn (?Party $p, string $no, string $type, float $price, $cur = null, string $status = 'Active') => Item::create(['party_id' => $p?->id, 'description' => 'Part '.$no, 'item_no' => $no,
            'type' => $type, 'hsn_sac_code_id' => $hsn->id, 'uom_id' => $nos->id, 'tax_structure_id' => $gst18->id, 'price' => $price, 'currency_id' => $cur ?? $inr, 'status' => $status]);

        return [
            'mh' => $mh, 'ka' => $ka, 'us' => $us, 'cust' => $cust, 'inactive' => $inactive, 'gst18' => $gst18, 'nos' => $nos,
            'casting' => $mk($mh, 'CST-4410', 'Purchase', 210),
            'cover' => $mk($mh, 'CST-4412', 'Purchase', 145),
            'common' => $mk(null, 'RM-CR-1.6', 'Both', 68),
            'salesOnly' => $mk(null, 'FG-1001', 'Sales', 500),
            'inactiveItem' => $mk($mh, 'CST-OLD', 'Purchase', 10, null, 'Inactive'),
            'kaPart' => $mk($ka, 'KAV-100', 'Purchase', 100),
            'tool' => $mk($us, 'OH-900', 'Purchase', 50, $usd->id),
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

    private function makePo(array $d, array $lines, ?Party $party = null, ?User $as = null, array $over = []): PurchaseOrder
    {
        $this->actingAs($as ?? $this->buyer())->post('/purchase/orders', $this->payload($party ?? $d['mh'], $lines, $over))->assertSessionHasNoErrors();

        return PurchaseOrder::latest('id')->first();
    }

    private function submit(PurchaseOrder $po): PurchaseOrder
    {
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/submit")->assertSessionHasNoErrors();

        return $po->fresh();
    }

    private function approved(array $d, array $lines): PurchaseOrder
    {
        $po = $this->submit($this->makePo($d, $lines));
        if ($po->status === PurchaseOrder::PENDING) {
            $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/approve")->assertSessionHasNoErrors();
        }
        $po = $po->fresh('lines');
        $this->assertSame(PurchaseOrder::APPROVED, $po->status);

        return $po;
    }

    private function refused(callable $fn, string $message): void
    {
        try {
            $fn();
            $this->fail($message);
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_supplier_must_be_an_active_supplier_or_both_and_items_follow_s4(): void
    {
        $d = $this->data();
        $b = $this->buyer();
        // customer-only and inactive parties are refused
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['cust'], [$this->line($d['common'], 1)]))->assertSessionHasErrors('party_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['inactive'], [$this->line($d['common'], 1)]))->assertSessionHasErrors('party_id');
        // S-4: Sales-only item, inactive item, another supplier's item are refused
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['salesOnly'], 1, '500')]))->assertSessionHasErrors('lines.0.item_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['inactiveItem'], 1)]))->assertSessionHasErrors('lines.0.item_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['kaPart'], 1)]))->assertSessionHasErrors('lines.0.item_id');
        $this->actingAs($b)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1), $this->line($d['tool'], 1, '5')]))->assertSessionHasErrors('lines.1.item_id');
        $this->assertSame(0, PurchaseOrder::count());
        // the service checks the party too (not only the form)
        $this->actingAs($b);
        $this->refused(fn () => app(PurchaseOrderService::class)->create(['party_id' => $d['cust']->id, 'po_date' => $this->today(), 'lines' => [$this->line($d['common'], 1)]],
            $b, Unit::first(), \App\Support\WorkContext::financialYear()), 'Customer accepted by the service');
        // a Both party is a supplier; its own items and items of no party are fine
        $po = $this->makePo($d, [$this->line($d['kaPart'], 2), $this->line($d['common'], 1)], $d['ka']);
        $this->assertCount(2, $po->lines);
        // the party picker and info lists suppliers only
        $this->actingAs($b)->getJson('/purchase/parties?q=apex')->assertOk()->assertExactJson([]);
        $this->actingAs($b)->getJson("/purchase/items?party={$d['mh']->id}&q=FG")->assertOk()->assertExactJson([]);
    }

    public function test_totals_and_gst_are_worked_out_on_the_server(): void
    {
        $d = $this->data();
        // posted totals (header and line) are ignored
        $lines = [$this->line($d['casting'], 3, '10.05', ['total' => '1', 'taxable_value' => '1', 'cgst_amount' => '999'])];
        $po = $this->makePo($d, $lines, null, null, ['taxable_value' => '1', 'grand_total' => '1', 'cgst_amount' => '5', 'status' => 'Approved', 'po_no' => 'X/1', 'supply_type' => 'Import']);
        $this->assertSame(PurchaseOrder::DRAFT, $po->status);
        $this->assertStringStartsWith('PO/', $po->po_no);
        $this->assertSame('Intra-state', $po->supply_type);
        $l = $po->lines->first();
        $this->assertSame('30.15', $l->taxable_value);          // 3 x 10.05
        $this->assertSame('2.71', $l->cgst_amount);             // 9% of 30.15 = 2.7135
        $this->assertSame('2.71', $l->sgst_amount);
        $this->assertSame('0.00', $l->igst_amount);
        $this->assertSame('35.57', $l->total);
        $this->assertSame('30.15', $po->taxable_value);
        $this->assertSame('35.57', $po->grand_total);

        // header totals are the sum of per-line rounded GST (as on the PDF and the page)
        $po2 = $this->makePo($d, [$this->line($d['casting'], 1, '10.05'), $this->line($d['cover'], 1, '10.05')]);
        $this->assertSame('20.10', $po2->taxable_value);
        $this->assertSame('1.80', $po2->cgst_amount);            // 0.90 + 0.90 (not 9% of 20.10 = 1.81)
        $this->assertSame('23.70', $po2->grand_total);

        // another state: IGST only
        $ka = $this->makePo($d, [$this->line($d['kaPart'], 3, '33.33')], $d['ka']);
        $this->assertSame('Inter-state', $ka->supply_type);
        $this->assertSame('99.99', $ka->taxable_value);
        $this->assertSame('0.00', $ka->cgst_amount);
        $this->assertSame('0.00', $ka->sgst_amount);
        $this->assertSame('18.00', $ka->igst_amount);           // 17.9982
        $this->assertSame('18.00', $ka->lines->first()->igst_rate);
        $this->assertSame('117.99', $ka->grand_total);

        // a supplier in another currency: no GST at all, even with a tax posted
        $us = $this->makePo($d, [$this->line($d['tool'], 10, '', ['tax_structure_id' => (string) $d['gst18']->id])], $d['us']);
        $this->assertSame('Import', $us->supply_type);
        $this->assertSame('USD', $us->currency->code);
        $this->assertSame('500.00', $us->taxable_value);
        $this->assertSame('0.00', $us->igst_amount);
        $this->assertSame('0.00', $us->lines->first()->igst_rate);
        $this->assertSame('500.00', $us->grand_total);

        // a change re-computes everything from the masters
        $this->actingAs($this->buyer())->put("/purchase/orders/{$po->id}", $this->payload($d['mh'], [$this->line($d['casting'], 10, '', ['id' => (string) $l->id, 'total' => '1'])], ['grand_total' => '1']))->assertSessionHasNoErrors();
        $this->assertSame('2478.00', $po->fresh()->grand_total);     // 10 x 210 + 18%

        // the PDF prints the stored totals
        $html = view('pdf.purchase-order', ['po' => $po2->fresh(['lines.item', 'lines.uom', 'party', 'currency', 'transporter', 'unit', 'approver']), 'company' => Company::current()])->render();
        $this->assertStringContainsString(Money::indian('23.70', 2), $html);
        $this->assertStringContainsString(Money::indian('1.80', 2), $html);
        $this->assertStringContainsString('NOT APPROVED', $html);
    }

    public function test_approval_threshold_is_on_the_amount_before_gst_and_foreign_currency_always_waits(): void
    {
        $d = $this->data();
        // exactly Rs 1,00,000 before GST (Rs 1,18,000 with GST): approved at once
        $at = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000, '100')]));
        $this->assertSame('118000.00', $at->grand_total);
        $this->assertSame(PurchaseOrder::APPROVED, $at->status);
        $this->assertNotNull($at->approved_at);
        $this->assertSame('Approved automatically', $at->approvals()->first()->action);
        // just under
        $this->assertSame(PurchaseOrder::APPROVED, $this->submit($this->makePo($d, [$this->line($d['casting'], 1, '99999.99')]))->status);
        // above
        $above = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000, '100.01')]));
        $this->assertSame(PurchaseOrder::PENDING, $above->status);
        $this->assertSame('Submitted', $above->approvals()->first()->action);
        $this->assertNull($above->approved_at);
        // foreign currency: always waits, however small
        $usd = $this->submit($this->makePo($d, [$this->line($d['tool'], 1)], $d['us']));
        $this->assertSame('50.00', $usd->taxable_value);
        $this->assertSame(PurchaseOrder::PENDING, $usd->status);
        // already sent: cannot be sent again
        $this->actingAs($this->buyer())->post("/purchase/orders/{$above->id}/submit")->assertSessionHasErrors('status');
        // approval switched off: approved at once even above the amount
        \App\Models\ApprovalRule::where('document_type', 'purchase_order')->update(['is_required' => false]);
        $this->assertSame(PurchaseOrder::APPROVED, $this->submit($this->makePo($d, [$this->line($d['casting'], 5000)]))->status);
    }

    public function test_approver_rights_reject_and_cancel_need_a_reason(): void
    {
        $d = $this->data();
        $po = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));
        $this->assertSame(PurchaseOrder::PENDING, $po->status);

        // the buyer (creator, no approval right) and a view-only approver are refused
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/approve")->assertForbidden();
        $this->actingAs($this->buyer())->post("/purchase/orders/{$po->id}/reject", ['remarks' => 'x'])->assertForbidden();
        Role::create(['name' => 'Approval viewer', 'guard_name' => 'web'])->givePermissionTo(['purchase-order-approval.view']);
        $viewer = $this->user('viewer@company.example', 'Approval viewer');
        $this->actingAs($viewer)->get("/purchase/orders/{$po->id}")->assertOk();
        $this->actingAs($viewer)->post("/purchase/orders/{$po->id}/approve")->assertForbidden();
        $this->actingAs($viewer)->post("/purchase/orders/{$po->id}/reject", ['remarks' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->post('/purchase/orders', $this->payload($d['mh'], [$this->line($d['casting'], 1)]))->assertForbidden();
        $this->assertSame(PurchaseOrder::PENDING, $po->fresh()->status);

        // an approver of another unit is refused too
        $u9 = Unit::create(['code' => 'U9', 'name' => 'Unit 9']);
        Role::create(['name' => 'Approver', 'guard_name' => 'web'])->givePermissionTo(['purchase-order-approval.view', 'purchase-order-approval.edit']);
        $far = $this->user('far@company.example', 'Approver', $u9);
        $this->actingAs($far)->post("/purchase/orders/{$po->id}/approve")->assertForbidden();
        $this->actingAs($far)->get("/purchase/orders/{$po->id}")->assertForbidden();

        // reject: reason required; kept on the PO, the approval row and the log
        $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/reject", ['remarks' => '   '])->assertSessionHasErrors('remarks');
        $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/reject", ['remarks' => 'Rate too high'])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame(PurchaseOrder::REJECTED, $po->status);
        $this->assertSame('Rate too high', $po->status_reason);
        $this->assertSame('Rejected', $po->approvals()->latest('id')->first()->action);
        $this->assertSame('Rate too high', $po->approvals()->latest('id')->first()->remarks);
        // a rejected PO is not pending: cannot be approved
        $this->actingAs($this->admin())->post("/purchase/orders/{$po->id}/approve")->assertSessionHasErrors('status');

        // cancel: reason required; the PO stays with its number, out of open POs; cannot be cancelled twice
        $ok = $this->approved($d, [$this->line($d['casting'], 10)]);
        $this->actingAs($this->buyer())->post("/purchase/orders/{$ok->id}/cancel", [])->assertSessionHasErrors('remarks');
        $this->actingAs($this->buyer())->post("/purchase/orders/{$ok->id}/cancel", ['remarks' => 'Supplier cannot deliver'])->assertSessionHasNoErrors();
        $ok->refresh();
        $this->assertSame(PurchaseOrder::CANCELLED, $ok->status);
        $this->assertSame('Supplier cannot deliver', $ok->status_reason);
        $this->assertNotNull($ok->po_no);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.status_changed')->where('subject_id', $ok->id)->where('description', 'like', '%Supplier cannot deliver%')->exists());
        $this->assertSame(0, PurchaseOrder::whereKey($ok->id)->pendingReceipt()->count());
        $this->actingAs($this->buyer())->post("/purchase/orders/{$ok->id}/cancel", ['remarks' => 'Again'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->put("/purchase/orders/{$ok->id}", $this->payload($d['mh'], [$this->line($d['casting'], 10)]))->assertSessionHasErrors('form');
        // cancel needs Purchase order · Delete: a view-only approver cannot
        $this->actingAs($viewer)->post("/purchase/orders/{$po->id}/cancel", ['remarks' => 'x'])->assertForbidden();
    }

    public function test_receive_contract(): void
    {
        $d = $this->data();
        $po = $this->approved($d, [$this->line($d['casting'], 10), $this->line($d['cover'], 5)]);
        [$a, $b] = [$po->lines[0], $po->lines[1]];
        $svc = app(PurchaseOrderService::class);
        $admin = $this->admin();
        $this->actingAs($admin);

        // partial
        $svc->receive($po, [$a->id => 4], $admin);
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        $this->assertSame('4.000', $a->fresh()->received_quantity);
        $this->assertSame(1, PurchaseOrder::pendingReceipt()->count());
        $this->assertSame([6.0, 5.0], [$a->fresh()->pending(), $b->fresh()->pending()]);
        // over-receipt refused, nothing changed (also when another line of the same call is fine)
        $this->refused(fn () => $svc->receive($po, [$a->id => 6.001], $admin), 'Over-receipt accepted');
        $this->refused(fn () => $svc->receive($po, [$b->id => 1, $a->id => 7], $admin), 'Over-receipt accepted');
        $this->assertSame('0.000', $b->fresh()->received_quantity);
        $this->assertSame('4.000', $a->fresh()->received_quantity);
        // a line of another PO refused
        $other = $this->approved($d, [$this->line($d['casting'], 1)]);
        $this->refused(fn () => $svc->receive($po, [$other->lines[0]->id => 1], $admin), 'Line of another PO accepted');
        // full
        $svc->receive($po, [$a->id => 6, $b->id => 5], $admin);
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);
        $this->assertSame(1, PurchaseOrder::pendingReceipt()->count());   // only $other
        $this->assertSame([0.0, 0.0], [$a->fresh()->pending(), $b->fresh()->pending()]);
        // a received PO cannot receive more
        $this->refused(fn () => $svc->receive($po, [$a->id => 1], $admin), 'Received more on a Received PO');
        // negative: back to Partly received, then Approved; never below 0
        $svc->receive($po, [$b->id => -5], $admin);
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);
        $svc->receive($po, [$a->id => -10], $admin);
        $this->assertSame(PurchaseOrder::APPROVED, $po->fresh()->status);
        $this->refused(fn () => $svc->receive($po, [$a->id => -0.001], $admin), 'Received went below 0');
        $this->assertSame('0.000', $a->fresh()->received_quantity);
        $this->assertTrue(ActivityLog::where('action', 'purchase_order.status_changed')->where('subject_id', $po->id)->where('description', 'like', '%received%')->exists());

        // refused on Draft, Pending approval, Rejected and Cancelled
        $draft = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $this->refused(fn () => $svc->receive($draft, [$draft->lines()->first()->id => 1], $admin), 'Received on a Draft');
        $pending = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));
        $this->refused(fn () => $svc->receive($pending, [$pending->lines()->first()->id => 1], $admin), 'Received on a Pending PO');
        $this->actingAs($admin)->post("/purchase/orders/{$pending->id}/reject", ['remarks' => 'No']);
        $this->refused(fn () => $svc->receive($pending, [$pending->lines()->first()->id => 1], $admin), 'Received on a Rejected PO');
        $this->actingAs($admin)->post("/purchase/orders/{$other->id}/cancel", ['remarks' => 'No']);
        $this->refused(fn () => $svc->receive($other, [$other->lines[0]->id => 1], $admin), 'Received on a Cancelled PO');
        $this->assertSame('0.000', $draft->lines()->first()->received_quantity);
    }

    public function test_delete_only_draft_or_rejected(): void
    {
        $d = $this->data();
        $b = $this->buyer();
        $draft = $this->makePo($d, [$this->line($d['casting'], 5)]);
        $this->actingAs($b)->delete("/purchase/orders/{$draft->id}")->assertSessionHasNoErrors()->assertRedirect(route('purchase.orders.index'));
        $this->assertSoftDeleted('purchase_orders', ['id' => $draft->id]);

        $rejected = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));
        $this->actingAs($this->admin())->post("/purchase/orders/{$rejected->id}/reject", ['remarks' => 'No']);
        $this->actingAs($b)->get("/purchase/orders/{$rejected->id}")->assertOk()->assertSee('Delete purchase order');
        $this->actingAs($b)->delete("/purchase/orders/{$rejected->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('purchase_orders', ['id' => $rejected->id]);

        $pending = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));
        $this->actingAs($b)->get("/purchase/orders/{$pending->id}")->assertOk()->assertDontSee('Delete purchase order');
        $this->actingAs($b)->delete("/purchase/orders/{$pending->id}")->assertSessionHasErrors('delete');
        $approved = $this->approved($d, [$this->line($d['casting'], 5)]);
        $this->actingAs($b)->delete("/purchase/orders/{$approved->id}")->assertSessionHasErrors('delete');
        $this->actingAs($this->admin())->post("/purchase/orders/{$approved->id}/cancel", ['remarks' => 'Not needed']);
        $this->actingAs($this->admin())->delete("/purchase/orders/{$approved->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $pending->id]);
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $approved->id]);
        // a deleted number is not reused
        $next = $this->makePo($d, [$this->line($d['casting'], 1)]);
        $this->assertStringEndsWith('/0005', $next->po_no);
    }

    public function test_pdf_renders_with_the_company_logo(): void
    {
        Storage::fake('local');
        config(['nursery.documents_disk' => 'local']);
        $d = $this->data();
        // the small JPG of the company logo test (JPG works without GD, as on the live server)
        $jpg = base64_decode((new \ReflectionClassConstant(CompanyLogoTest::class, 'JPG'))->getValue());
        Storage::disk('local')->put('company/logo.jpg', $jpg);
        Company::current()->forceFill(['logo_path' => 'company/logo.jpg'])->save();
        $po = $this->approved($d, [$this->line($d['casting'], 5)]);

        $html = view('pdf.purchase-order', ['po' => $po->fresh(['lines.item', 'lines.uom', 'party', 'currency', 'transporter', 'unit', 'approver']), 'company' => Company::current()])->render();
        $this->assertStringContainsString('src="data:image/jpeg;base64,', $html);
        $this->assertStringContainsString($po->po_no, $html);
        $this->assertStringContainsString('Shree Ganesh Castings', $html);
        $this->assertStringNotContainsString('NOT APPROVED', $html);

        $res = $this->actingAs($this->buyer())->get("/purchase/orders/{$po->id}/pdf")->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString(str_replace('/', '-', $po->po_no).'.pdf', $res->headers->get('content-disposition'));
        // another role cannot download it
        $this->actingAs($this->user('sales@company.example', 'Sales'))->get("/purchase/orders/{$po->id}/pdf")->assertForbidden();
    }

    public function test_list_views_count_by_status(): void
    {
        $d = $this->data();
        $b = $this->buyer();
        $this->makePo($d, [$this->line($d['casting'], 1)]);                                              // Draft
        $rej = $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));                     // Rejected
        $this->actingAs($this->admin())->post("/purchase/orders/{$rej->id}/reject", ['remarks' => 'No']);
        $this->submit($this->makePo($d, [$this->line($d['casting'], 1000)]));                            // Pending
        $this->approved($d, [$this->line($d['casting'], 2)]);                                             // Approved
        $partly = $this->approved($d, [$this->line($d['casting'], 4)]);                                   // Partly received
        $full = $this->approved($d, [$this->line($d['casting'], 3)]);                                     // Received
        $cancel = $this->approved($d, [$this->line($d['casting'], 3)]);                                   // Cancelled
        $this->actingAs($this->admin());
        app(PurchaseOrderService::class)->receive($partly, [$partly->lines[0]->id => 1], $this->admin());
        app(PurchaseOrderService::class)->receive($full, [$full->lines[0]->id => 3], $this->admin());
        $this->actingAs($b)->post("/purchase/orders/{$cancel->id}/cancel", ['remarks' => 'No']);

        $count = fn ($view) => $this->actingAs($b)->get('/purchase/orders/list'.($view ? '?view='.$view : ''))->assertOk()->viewData('rows')->total();
        $this->assertSame(7, $count(null));
        $this->assertSame(1, $count('pending'));
        $this->assertSame(1, $count('approved'));
        $this->assertSame(2, $count('open'));         // Approved + Partly received
        $this->assertSame(2, $count('draft'));        // Draft + Rejected
        $this->assertSame(7, $count('nonsense'));
        // search keeps the view; another unit's POs are not listed
        $this->assertSame(0, $this->actingAs($b)->get('/purchase/orders/list?view=open&q=apex')->viewData('rows')->total());
        $u9 = Unit::create(['code' => 'U9', 'name' => 'Unit 9']);
        $far = $this->user('far@company.example', 'Purchase', $u9);
        $this->assertSame(0, $this->actingAs($far)->get('/purchase/orders/list')->viewData('rows')->total());
        $this->actingAs($far)->get("/purchase/orders/{$partly->id}")->assertForbidden();
        $this->actingAs($far)->get("/purchase/orders/{$partly->id}/pdf")->assertForbidden();
        // a PO of the other unit starts its own number
        $farPo = $this->makePo($d, [$this->line($d['casting'], 1)], null, $far);
        $this->assertSame($u9->id, $farPo->unit_id);
        $this->assertStringEndsWith('/0001', $farPo->po_no);
    }

    public function test_old_address_redirects_and_is_refused_without_the_right(): void
    {
        $this->data();
        $this->actingAs($this->buyer())->get('/app/po')->assertRedirect(route('purchase.orders.index'));
        $this->actingAs($this->admin())->get('/app/po')->assertRedirect(route('purchase.orders.index'));
        $sales = $this->user('sales@company.example', 'Sales');
        $this->actingAs($sales)->get('/app/po')->assertForbidden();
        $this->actingAs($sales)->get('/purchase/orders')->assertForbidden();
        $this->actingAs($sales)->get('/purchase/orders/list')->assertForbidden();
        $this->actingAs($sales)->post('/purchase/orders', [])->assertForbidden();
    }
}
