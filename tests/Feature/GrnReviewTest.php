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
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\GrnService;
use App\Services\PageSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Review of Purchase › GRN entry (27/10/2026): tolerance, re-save, over-receipt races (sequential simulation), PO sent
 * back for approval, invoice uniqueness, cancel after the stock moved on, credit / debit notes, lines without PO,
 * permissions and unit scoping, list views. Test database only.
 */
class GrnReviewTest extends TestCase
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
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '7325', 'type' => 'HSN', 'description' => 'Cast articles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn (?Party $p, string $no, float $price, $tax) => Item::create(['party_id' => $p?->id, 'description' => 'Part '.$no, 'item_no' => $no, 'type' => 'Purchase',
            'hsn_sac_code_id' => $hsn->id, 'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'currency_id' => $inr, 'status' => 'Active']);
        [$u1, $u2] = Unit::orderBy('id')->get()->all();
        $this->d = ['mh' => $mh, 'ka' => $ka, 'casting' => $mk($mh, 'CST-4410', 210, $gst18), 'cover' => $mk($mh, 'CST-4412', 145, $gst12),
            'kaPart' => $mk($ka, 'KAV-100', 100, $gst18), 'unit' => $u1, 'unit2' => $u2];
    }

    // ---- helpers ----------------------------------------------------------------------------------------------------

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role, ?array $units = null): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98224'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync($units ?? [$this->d['unit']->id]);

        return $u;
    }

    private function buyer(): User
    {
        return $this->user('rahul@company.example', 'Purchase');
    }

    /** Approved PO of Shree Ganesh: casting 1000 @ 210 (GST 18%), cover 300 @ 145 (GST 12%). */
    private function approvedPo(?Party $party = null, ?array $lines = null): PurchaseOrder
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
            'invoice_amount' => '172620.00', 'transporter_id' => '', 'vehicle_no' => '', 'eway_bill_no' => '', 'eway_bill_date' => '',
            'customer_id' => '', 'sales_order_id' => '', 'remarks' => '', 'intent' => 'pending', 'lines_json' => json_encode($lines)], $over);
    }

    /** The validated array the service takes (as GrnRequest hands it over), for driving GrnService directly. */
    private function data(array $lines, array $over = []): array
    {
        $p = $this->payload($lines, $over);
        unset($p['lines_json'], $p['intent']);

        return $p + ['lines' => $lines];
    }

    private function line(PurchaseOrder $po, int $n, $qty, $rate = ''): array
    {
        $l = $po->lines->firstWhere('line_no', $n);

        return ['purchase_order_id' => (string) $po->id, 'purchase_order_line_id' => (string) $l->id, 'quantity' => (string) $qty, 'rate' => (string) $rate];
    }

    private function received(PurchaseOrder $po, int $n): string
    {
        return (string) $po->lines()->where('line_no', $n)->value('received_quantity');
    }

    private function balance(Item $item, ?Unit $unit = null): float
    {
        return (float) DB::table('stock_balances')->where('unit_id', ($unit ?? $this->d['unit'])->id)->where('item_id', $item->id)->value('quantity');
    }

    /** casting 500 @ 210 + 18% = 1,23,900; cover 300 @ 145 + 12% = 48,720; total 1,72,620. */
    private function matchedGrn(PurchaseOrder $po, array $over = []): Grn
    {
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)], $over + ['intent' => 'match']))
            ->assertSessionHasNoErrors();

        return Grn::latest('id')->first();
    }

    private function pendingGrn(array $lines, array $over = []): Grn
    {
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload($lines, $over + ['invoice_amount' => '1.00']))->assertSessionHasNoErrors();
        $g = Grn::latest('id')->first();
        $this->assertSame(Grn::PENDING_MATCH, $g->match_status);

        return $g;
    }

    // ---- match tolerance and re-save -------------------------------------------------------------------------------

    public function test_match_tolerance_is_one_rupee_either_side_and_a_miss_saves_nothing(): void
    {
        $po = $this->approvedPo();
        $buyer = $this->buyer();
        $casting = fn ($q) => [$this->line($po, 1, $q)];   // 500 → 1,23,900.00

        // ₹1.01 above: refused, nothing saved, PO untouched.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($casting(500), ['invoice_no' => 'T-1', 'invoice_amount' => '1,23,901.01', 'intent' => 'match']))
            ->assertSessionHasErrors('invoice_amount');
        $this->assertSame(0, Grn::withTrashed()->count());
        $this->assertSame('0.000', $this->received($po, 1));
        // Exactly ₹1 above: matched.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($casting(500), ['invoice_no' => 'T-1', 'invoice_amount' => '123901.00', 'intent' => 'match']))
            ->assertSessionHasNoErrors();
        $this->assertSame(Grn::MATCHED, Grn::latest('id')->first()->match_status);

        // ₹1.01 below: refused; exactly ₹1 below: matched.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($casting(500), ['invoice_no' => 'T-2', 'invoice_amount' => '123898.99', 'intent' => 'match']))
            ->assertSessionHasErrors('invoice_amount');
        $this->assertSame(1, Grn::count());
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($casting(500), ['invoice_no' => 'T-2', 'invoice_amount' => '123899', 'intent' => 'match']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Grn::whereNotNull('grn_no')->count());
        $this->assertSame('1000.000', $this->received($po, 1));
        $this->assertSame(PurchaseOrder::PARTLY_RECEIVED, $po->fresh()->status);

        // The service check itself (not only the form): ±1 on the rupee, inclusive.
        $svc = app(GrnService::class);
        $g = new Grn(['invoice_amount' => '100.00']);
        foreach (['99.00' => true, '101.00' => true, '98.99' => false, '101.01' => false] as $total => $ok) {
            $g->grand_total = $total;
            $this->assertSame($ok, $svc->amountMatches($g), $total);
        }
    }

    public function test_resaving_a_matched_grn_does_not_receive_the_po_twice(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $same = $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)], ['intent' => 'match']);
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $same)->assertSessionHasNoErrors();
        }
        $this->assertSame('500.000', $this->received($po, 1));
        $this->assertSame('300.000', $this->received($po, 2));
        $this->assertSame($grn->grn_no, $grn->fresh()->grn_no);
        $this->assertSame(1, ActivityLog::where('action', 'grn.matched')->where('subject_id', $grn->id)->count());
        // Nothing changed: no update rows in the log either.
        $this->assertSame(0, ActivityLog::where('action', 'grn.updated')->where('subject_id', $grn->id)->count());

        // Moving 100 from casting to … nothing: lower casting to 400 with the matching amount → PO moves by -100 only.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 400), $this->line($po, 2, 300)], ['invoice_amount' => '147840']))
            ->assertSessionHasNoErrors();
        $this->assertSame('400.000', $this->received($po, 1));
        // Dropping the cover line: the cover quantity goes back (lines total 99,120).
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 400)], ['invoice_amount' => '99120']))
            ->assertSessionHasNoErrors();
        $this->assertSame('0.000', $this->received($po, 2));
        $this->assertSame('400.000', $this->received($po, 1));
        $this->assertTrue(ActivityLog::where('action', 'grn.updated')->where('subject_id', $grn->id)->exists());

        // A pending GRN saved again and again receives nothing.
        $p = $this->pendingGrn([$this->line($po->fresh('lines'), 1, 100)], ['invoice_no' => 'P-1']);
        $this->actingAs($this->buyer())->put("/purchase/grns/{$p->id}", $this->payload([$this->line($po->fresh('lines'), 1, 100)], ['invoice_no' => 'P-1', 'invoice_amount' => '5']))
            ->assertSessionHasNoErrors();
        $this->assertSame('400.000', $this->received($po, 1));
    }

    public function test_matched_grn_keeps_supplier_and_invoice_type_and_must_stay_matched(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $lines = [$this->line($po, 1, 500), $this->line($po, 2, 300)];
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload($lines, ['invoice_type' => 'Credit']))->assertSessionHasErrors();
        // The service refuses it too (the form already refuses PO lines on a credit note).
        try {
            app(GrnService::class)->update($grn, $this->data($lines, ['invoice_type' => 'Debit']), $this->buyer());
            $this->fail('Invoice type changed on a matched GRN');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('invoice_type', $e->errors());
        }
        // Lines total no longer matching: refused, nothing changes.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 499), $this->line($po, 2, 300)]))->assertSessionHasErrors('invoice_amount');
        $this->assertSame('500.000', $this->received($po, 1));
        $this->assertSame('172620.00', (string) $grn->fresh()->grand_total);
        $this->assertSame('Purchase', $grn->fresh()->invoice_type);
    }

    // ---- over-receipt races (two users working at once, simulated in sequence) -------------------------------------

    public function test_two_grns_on_the_same_po_line_cannot_receive_more_than_pending(): void
    {
        $po = $this->approvedPo();
        // Both saved as pending match while 1000 were pending: neither reserves anything.
        $a = $this->pendingGrn([$this->line($po, 1, 800)], ['invoice_no' => 'A-1']);
        $b = $this->pendingGrn([$this->line($po, 1, 800)], ['invoice_no' => 'B-1']);
        $this->assertSame('0.000', $this->received($po, 1));

        // A matches (800 @ 210 + 18% = 1,98,240).
        $this->actingAs($this->buyer())->put("/purchase/grns/{$a->id}", $this->payload([$this->line($po, 1, 800)], ['invoice_no' => 'A-1', 'invoice_amount' => '198240', 'intent' => 'match']))
            ->assertSessionHasNoErrors();
        $this->assertSame('800.000', $this->received($po, 1));

        // B through the form: the pending check refuses it.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$b->id}", $this->payload([$this->line($po, 1, 800)], ['invoice_no' => 'B-1', 'invoice_amount' => '198240', 'intent' => 'match']))
            ->assertSessionHasErrors('lines.0.quantity');
        // B as if its form check ran before A committed (the race): receive() re-checks under the PO lock and refuses.
        try {
            app(GrnService::class)->update($b, $this->data([$this->line($po, 1, 800)], ['invoice_no' => 'B-1', 'invoice_amount' => '198240']), $this->buyer(), true);
            $this->fail('Over-received the PO line');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('pending', implode(' ', $e->validator->errors()->all()));
        }
        $b->refresh();
        $this->assertSame(Grn::PENDING_MATCH, $b->match_status);
        $this->assertNull($b->grn_no);
        $this->assertSame('1.00', (string) $b->invoice_amount, 'rolled back: B still has its old amount');
        $this->assertSame('800.000', $this->received($po, 1));

        // Same race on a new GRN: create() is refused whole, nothing saved, no number used.
        $before = Grn::withTrashed()->count();
        try {
            app(GrnService::class)->create($this->data([$this->line($po, 1, 201)], ['invoice_no' => 'C-1', 'invoice_amount' => '49795.80']), $this->buyer(), $this->d['unit'],
                \App\Support\WorkContext::financialYear(), true);
            $this->fail('Over-received the PO line');
        } catch (ValidationException) {
        }
        $this->assertSame($before, Grn::withTrashed()->count());
        // Exactly what is left (200) is fine and takes the next number (0002: no number was burnt).
        $this->actingAs($this->buyer())->put("/purchase/grns/{$b->id}", $this->payload([$this->line($po, 1, 200)], ['invoice_no' => 'B-1', 'invoice_amount' => '49560', 'intent' => 'match']))
            ->assertSessionHasNoErrors();
        $this->assertStringEndsWith('/0002', $b->fresh()->grn_no);
        $this->assertSame('1000.000', $this->received($po, 1));
    }

    public function test_a_matched_grn_raised_by_an_edit_is_also_capped_under_lock(): void
    {
        $po = $this->approvedPo();
        $a = $this->matchedGrn($po);                                                       // casting 500 received
        $b = $this->pendingGrn([$this->line($po, 1, 500)], ['invoice_no' => 'B-1']);
        $this->actingAs($this->buyer())->put("/purchase/grns/{$b->id}", $this->payload([$this->line($po, 1, 500)], ['invoice_no' => 'B-1', 'invoice_amount' => '123900']))
            ->assertSessionHasNoErrors();                                                  // casting 1000 received
        $this->assertSame('1000.000', $this->received($po, 1));
        // A raises its casting to 600 with a matching amount (simulating a form check made before B matched).
        try {
            app(GrnService::class)->update($a, $this->data([$this->line($po, 1, 600), $this->line($po, 2, 300)], ['invoice_amount' => '197400']), $this->buyer());
            $this->fail('Over-received through an edit');
        } catch (ValidationException) {
        }
        $this->assertSame('500.000', (string) $a->lines()->where('line_no', 1)->value('quantity'));
        $this->assertSame('1000.000', $this->received($po, 1));
    }

    // ---- PO sent back for approval while GRNs are open --------------------------------------------------------------

    public function test_po_sent_back_for_approval_blocks_receiving_but_not_giving_back(): void
    {
        $po = $this->approvedPo();
        $matched = $this->matchedGrn($po);
        $pending = $this->pendingGrn([$this->line($po, 1, 100)], ['invoice_no' => 'P-1']);
        // An Admin edit sent the PO back to Pending approval (simulated).
        $po->forceFill(['status' => PurchaseOrder::PENDING])->save();

        // The pending GRN cannot match against it: refused by the form, and by receive() under lock.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$pending->id}", $this->payload([$this->line($po, 1, 100)], ['invoice_no' => 'P-1', 'invoice_amount' => '24780', 'intent' => 'match']))
            ->assertSessionHasErrors('lines.0.purchase_order_line_id');
        try {
            app(GrnService::class)->update($pending, $this->data([$this->line($po, 1, 100)], ['invoice_no' => 'P-1', 'invoice_amount' => '24780']), $this->buyer());
            $this->fail('Received against a PO pending approval');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Pending', implode(' ', $e->validator->errors()->all()));
        }
        $this->assertSame(Grn::PENDING_MATCH, $pending->fresh()->match_status);

        // The matched GRN: raising a quantity is refused, lowering it is allowed (quantity goes back to the PO).
        try {
            app(GrnService::class)->update($matched, $this->data([$this->line($po, 1, 600), $this->line($po, 2, 300)], ['invoice_amount' => '197400']), $this->buyer());
            $this->fail('Received more against a PO pending approval');
        } catch (ValidationException) {
        }
        $this->actingAs($this->buyer())->put("/purchase/grns/{$matched->id}", $this->payload([$this->line($po, 1, 400), $this->line($po, 2, 300)], ['invoice_amount' => '147840']))
            ->assertSessionHasNoErrors();
        $this->assertSame('400.000', $this->received($po, 1));
        $this->assertSame(PurchaseOrder::PENDING, $po->fresh()->status, 'receive() leaves a pending PO pending');

        // Quality rejection gives the quantities back even now; cancel then has nothing more to take back.
        $this->actingAs($this->admin())->post("/purchase/grns/{$matched->id}/quality", ['result' => 'Rejected', 'remarks' => 'Cracks'])->assertSessionHasNoErrors();
        $this->assertSame('0.000', $this->received($po, 1));
        $this->assertSame('0.000', $this->received($po, 2));
        $this->actingAs($this->admin())->post("/purchase/grns/{$matched->id}/cancel", ['remarks' => 'Supplier took back'])->assertSessionHasNoErrors();
        $this->assertSame('0.000', $this->received($po, 1));
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());
    }

    // ---- supplier invoice number ------------------------------------------------------------------------------------

    public function test_invoice_no_is_unique_per_supplier_and_year_among_live_grns_only(): void
    {
        $po = $this->approvedPo();
        $first = $this->pendingGrn([$this->line($po, 1, 10)], ['invoice_no' => 'INV-7']);
        // Same supplier, same number (spaces trimmed): refused.
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)], ['invoice_no' => '  INV-7 ', 'invoice_amount' => '1']))
            ->assertSessionHasErrors('invoice_no');
        $this->assertStringContainsString('already entered', session('errors')->first('invoice_no'));
        // Another supplier may use the same number.
        $this->actingAs($this->buyer())->post('/purchase/grns', $this->payload([['item_id' => (string) $this->d['kaPart']->id, 'quantity' => '1', 'rate' => '100']],
            ['invoice_type' => 'Debit', 'party_id' => $this->d['ka']->id, 'invoice_no' => 'INV-7', 'invoice_amount' => '1']))->assertSessionHasNoErrors();
        // Editing the GRN itself keeps its own number.
        $this->actingAs($this->buyer())->put("/purchase/grns/{$first->id}", $this->payload([$this->line($po, 1, 12)], ['invoice_no' => 'INV-7', 'invoice_amount' => '1']))
            ->assertSessionHasNoErrors();

        // Deleted (soft) frees the number.
        $this->actingAs($this->buyer())->delete("/purchase/grns/{$first->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('grns', ['id' => $first->id, 'active_invoice_no' => null]);
        $this->assertTrue(ActivityLog::where('action', 'grn.deleted')->where('subject_id', $first->id)->exists());
        $second = $this->matchedGrn($po, ['invoice_no' => 'INV-7']);
        // Cancelled frees it too; the number of the cancelled GRN stays.
        $this->actingAs($this->admin())->post("/purchase/grns/{$second->id}/cancel", ['remarks' => 'Duplicate'])->assertSessionHasNoErrors();
        $this->assertNull($second->fresh()->active_invoice_no);
        $this->assertSame('INV-7', $second->fresh()->invoice_no);
        $this->pendingGrn([$this->line($po->fresh('lines'), 1, 10)], ['invoice_no' => 'INV-7']);
        $this->assertSame(1, Grn::where('party_id', $this->d['mh']->id)->where('active_invoice_no', 'INV-7')->count());
    }

    // ---- cancel after the stock moved on ----------------------------------------------------------------------------

    public function test_cancel_is_refused_once_a_stock_transfer_moved_the_stock_on(): void
    {
        $admin = $this->admin();
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved', 'rejected' => [$grn->lines()->where('line_no', 2)->value('id') => '100']])
            ->assertSessionHasNoErrors();
        $this->assertSame(500.0, $this->balance($this->d['casting']));
        $this->assertSame(200.0, $this->balance($this->d['cover']));
        $this->assertSame('200.000', $this->received($po, 2));

        // 450 castings go to Unit 2 by an approved stock transfer.
        $this->actingAs($admin)->post('/purchase/stock-transfers', ['transfer_date' => today()->format('d/m/Y'), 'from_unit_id' => $this->d['unit']->id, 'to_unit_id' => $this->d['unit2']->id,
            'source_type' => 'Purchase', 'remarks' => '', '_form' => 'transfer', 'lines' => [['item_id' => $this->d['casting']->id, 'quantity' => '450', 'remarks' => '']]])->assertSessionHasNoErrors();
        $t = StockTransfer::latest('id')->first();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        if ($t->fresh()->status !== StockTransfer::APPROVED) {
            $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();
        }
        $this->assertSame(50.0, $this->balance($this->d['casting']));

        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'Wrong supplier'])->assertSessionHasErrors('status');
        $msg = session('errors')->first('status');
        $this->assertStringContainsString('CST-4410', $msg);
        $this->assertStringNotContainsString('CST-4412', $msg);
        // Nothing changed: GRN live, PO quantities and stock as they were.
        $this->assertSame(Grn::SAVED, $grn->fresh()->status);
        $this->assertTrue($grn->fresh()->stock_posted);
        $this->assertSame('500.000', $this->received($po, 1));
        $this->assertSame('200.000', $this->received($po, 2));
        $this->assertSame(50.0, $this->balance($this->d['casting']));
        $this->assertSame(2, StockMovement::where('source_type', 'grn')->where('source_id', $grn->id)->count());

        // The transfer cancelled (stock back at Unit 1): the GRN cancel goes through and takes back only what came in.
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Not sent'])->assertSessionHasNoErrors();
        $this->assertSame(500.0, $this->balance($this->d['casting']));
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'Wrong supplier'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance($this->d['casting']));
        $this->assertSame(0.0, $this->balance($this->d['cover']));
        $this->assertSame('0.000', $this->received($po, 1));
        $this->assertSame('0.000', $this->received($po, 2), 'the 100 rejected were already given back, not taken twice');
        $this->assertSame(PurchaseOrder::APPROVED, $po->fresh()->status);
        // Cancel is final.
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'again'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->delete("/purchase/grns/{$grn->id}")->assertSessionHasErrors('delete');
    }

    public function test_cancel_is_refused_when_other_stock_left_the_unit_too(): void
    {
        // Stock held is checked per unit and item, not per GRN: an issue of another document leaves too little.
        $admin = $this->admin();
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        DB::transaction(fn () => \App\Services\StockLedger::move($this->d['unit']->id, $this->d['cover']->id, today()->format('Y-m-d'), 0, 1, 'sales_invoice', 999));
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'x'])->assertSessionHasErrors('status');
        $this->assertStringContainsString('CST-4412', session('errors')->first('status'));
        $this->assertSame(Grn::SAVED, $grn->fresh()->status);
    }

    // ---- credit / debit notes ---------------------------------------------------------------------------------------

    public function test_credit_and_debit_notes_take_no_po_and_move_no_stock(): void
    {
        $po = $this->approvedPo();
        $buyer = $this->buyer();
        // Debit note pending match, then matched: number from the same series, approved at once, no stock, no PO.
        $item = fn ($q, $rate = '145') => [['item_id' => (string) $this->d['cover']->id, 'quantity' => (string) $q, 'rate' => $rate]];
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($item(10), ['invoice_type' => 'Debit', 'invoice_no' => 'DN-1', 'invoice_amount' => '1']))->assertSessionHasNoErrors();
        $dn = Grn::latest('id')->first();
        $this->assertSame([Grn::PENDING_MATCH, Grn::QA_PENDING], [$dn->match_status, $dn->quality_status]);
        // 10 @ 145 + 12% = 1,624.
        $this->actingAs($buyer)->put("/purchase/grns/{$dn->id}", $this->payload($item(10), ['invoice_type' => 'Debit', 'invoice_no' => 'DN-1', 'invoice_amount' => '1624', 'intent' => 'match']))
            ->assertSessionHasNoErrors();
        $dn->refresh();
        $this->assertSame([Grn::MATCHED, Grn::QA_APPROVED, false], [$dn->match_status, $dn->quality_status, $dn->stock_posted]);
        $this->assertStringEndsWith('/0001', $dn->grn_no);
        $this->assertSame('1624.00', (string) $dn->grand_total);
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());
        $this->assertSame('0.000', $this->received($po, 2));
        $this->assertTrue(ActivityLog::where('action', 'grn.quality')->where('subject_id', $dn->id)->exists());
        // A purchase GRN takes the next number.
        $this->assertStringEndsWith('/0002', $this->matchedGrn($po)->grn_no);

        // A credit note: an item of another supplier, or a PO line, is refused.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([['item_id' => (string) $this->d['kaPart']->id, 'quantity' => '1', 'rate' => '']],
            ['invoice_type' => 'Credit', 'invoice_no' => 'CN-1']))->assertSessionHasErrors('lines.0.item_id');
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([$this->line($po, 1, 1)], ['invoice_type' => 'Credit', 'invoice_no' => 'CN-1']))
            ->assertSessionHasErrors('lines.0.purchase_order_line_id');
        // A Purchase user (no quality right) may cancel a note: no stock came in.
        $this->actingAs($buyer)->post("/purchase/grns/{$dn->id}/cancel", ['remarks' => 'Entered twice'])->assertSessionHasNoErrors();
        $this->assertSame(Grn::CANCELLED, $dn->fresh()->status);
        // A note's quality decision cannot be given again.
        $this->actingAs($this->admin())->post("/purchase/grns/{$dn->id}/quality", ['result' => 'Rejected', 'remarks' => 'x'])->assertSessionHasErrors('quality');
    }

    // ---- lines without PO ------------------------------------------------------------------------------------------

    public function test_lines_without_po_switch(): void
    {
        $buyer = $this->buyer();
        $noPo = [['item_id' => (string) $this->d['casting']->id, 'quantity' => '10', 'rate' => '']];
        // Off (default): a Purchase line needs a PO line.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($noPo, ['invoice_amount' => '2478', 'intent' => 'match']))->assertSessionHasErrors('lines.0.purchase_order_line_id');
        $this->assertSame(0, Grn::count());

        config(['nursery.grn.lines_without_po' => true]);
        $this->actingAs($buyer)->get('/purchase/grns/create')->assertOk()->assertSee('Without a PO no, the supplier', false);
        // Another supplier's item is refused.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload([['item_id' => (string) $this->d['kaPart']->id, 'quantity' => '1', 'rate' => '']], ['intent' => 'match']))
            ->assertSessionHasErrors('lines.0.item_id');
        // Rate blank = item price 210: 10 × 210 + 18% = 2,478 → matched, PO untouched, stock at quality approval.
        $this->actingAs($buyer)->post('/purchase/grns', $this->payload($noPo, ['invoice_amount' => '2478', 'intent' => 'match']))->assertSessionHasNoErrors();
        $grn = Grn::latest('id')->first();
        $this->assertSame(Grn::MATCHED, $grn->match_status);
        $this->assertNull($grn->lines()->first()->purchase_order_line_id);
        $this->assertSame('210.0000', (string) $grn->lines()->first()->rate);
        $this->assertSame(0.0, $this->balance($this->d['casting']));
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame(10.0, $this->balance($this->d['casting']));
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance($this->d['casting']));
    }

    // ---- permissions and unit scoping ------------------------------------------------------------------------------

    public function test_quality_right_is_admin_only_and_checked_on_the_server(): void
    {
        $this->assertFalse(Role::findByName('Purchase')->hasPermissionTo('grn-quality.edit'));
        $this->assertTrue($this->admin()->can('grn-quality.edit'));
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);

        $this->actingAs($this->buyer())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertForbidden();
        $html = $this->actingAs($this->buyer())->get("/purchase/grns/{$grn->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('Approve quality', $html);
        $this->assertStringContainsString('Waiting for quality approval', $html);

        // A viewer of quality only: sees the GRN, cannot decide, cannot change it.
        $role = Role::create(['name' => 'QA viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('grn-quality.view');
        $qa = $this->user('qa@company.example', 'QA viewer');
        $this->actingAs($qa)->get("/purchase/grns/{$grn->id}")->assertOk();
        $this->actingAs($qa)->get('/purchase/grns/list')->assertOk();
        $this->actingAs($qa)->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertForbidden();
        $this->actingAs($qa)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)]))->assertForbidden();
        $this->actingAs($qa)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'x'])->assertForbidden();

        // An approver holding grn-quality.edit: the buttons show and the decision goes through.
        $role->givePermissionTo('grn-quality.edit');
        $this->actingAs($qa->fresh())->get("/purchase/grns/{$grn->id}")->assertOk()->assertSee('Approve quality');
        $this->actingAs($qa->fresh())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame($qa->id, $grn->fresh()->quality_by);
        $this->assertSame(500.0, $this->balance($this->d['casting']));
    }

    public function test_a_user_of_another_unit_gets_403_on_every_action(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        $pending = $this->pendingGrn([$this->line($po, 1, 10)], ['invoice_no' => 'P-1']);
        $u3 = Unit::create(['code' => 'U3', 'name' => 'Unit 3']);
        $far = $this->user('far@company.example', 'Purchase', [$u3->id]);
        $farAdmin = $this->user('faradmin@company.example', 'Admin', [$u3->id]);

        $this->actingAs($far)->get("/purchase/grns/{$grn->id}")->assertForbidden();
        $this->actingAs($far)->get("/purchase/grns?id={$grn->id}")->assertRedirect(route('purchase.grns.show', $grn->id));
        $this->actingAs($far)->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po, 1, 500), $this->line($po, 2, 300)], ['intent' => 'match']))->assertForbidden();
        $this->actingAs($far)->delete("/purchase/grns/{$pending->id}")->assertForbidden();
        $this->actingAs($far)->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'x'])->assertForbidden();
        $this->actingAs($far)->getJson("/purchase/grns/orders?party={$this->d['mh']->id}&grn={$grn->id}")->assertForbidden();
        $this->actingAs($farAdmin)->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertForbidden();
        // Their list, lookup and picker show nothing of Unit 1.
        $this->actingAs($far)->get('/purchase/grns/list')->assertOk()->assertDontSee('SGC/2026/1187');
        $this->assertSame([], $this->actingAs($far)->getJson('/purchase/grns/lookup?q=SGC')->assertOk()->json());
        $this->assertSame([], $this->actingAs($far)->getJson("/purchase/grns/orders?party={$this->d['mh']->id}")->assertOk()->json());
        // Their new GRN cannot use a Unit 1 PO.
        $this->actingAs($far)->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)], ['invoice_no' => 'FAR-1']))->assertSessionHasErrors('lines.0.purchase_order_line_id');

        // Nothing changed.
        $this->assertSame(Grn::QA_PENDING, $grn->fresh()->quality_status);
        $this->assertSame(Grn::SAVED, $grn->fresh()->status);
        $this->assertNull($pending->fresh()->deleted_at);
        // A Sales user has no GRN right at all.
        $sales = $this->user('priya@company.example', 'Sales');
        $this->actingAs($sales)->get("/purchase/grns/{$grn->id}")->assertForbidden();
        $this->actingAs($sales)->post('/purchase/grns', $this->payload([$this->line($po, 1, 10)], ['invoice_no' => 'S-1']))->assertForbidden();
        $this->actingAs($sales)->get('/app/grn')->assertForbidden();
    }

    public function test_cancel_after_quality_needs_the_quality_right_and_delete_needs_pending_match(): void
    {
        $po = $this->approvedPo();
        $grn = $this->matchedGrn($po);
        // Before the quality decision a Purchase user (GRN · Delete) may cancel.
        $this->actingAs($this->buyer())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($this->buyer())->post("/purchase/grns/{$grn->id}/cancel", ['remarks' => 'Typed wrong'])->assertSessionHasNoErrors();
        $this->assertSame('0.000', $this->received($po, 1));
        $this->assertTrue(ActivityLog::where('action', 'grn.cancelled')->where('subject_id', $grn->id)->exists());
        // A cancelled GRN cannot be changed or decided.
        $this->actingAs($this->admin())->put("/purchase/grns/{$grn->id}", $this->payload([$this->line($po->fresh('lines'), 1, 500)]))->assertSessionHasErrors('form');
        $this->actingAs($this->admin())->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasErrors('quality');
        $this->assertSame(0, StockMovement::where('source_type', 'grn')->count());

        // With Quality approval hidden, a Purchase GRN is approved at match, so only a quality approver can cancel it.
        app(PageSetupService::class)->save('grn', collect(app(PageSetupService::class)->fields('grn'))
            ->map(fn ($f, $k) => ['visible' => $k !== 'quality_status', 'required' => $f['required']])->all(), $this->admin());
        $auto = $this->matchedGrn($po->fresh('lines'), ['invoice_no' => 'AUTO-1']);
        $this->assertSame([Grn::QA_APPROVED, true], [$auto->quality_status, $auto->stock_posted]);
        $this->assertSame(500.0, $this->balance($this->d['casting']));
        $this->assertSame($auto->grn_date->format('Y-m-d'), StockMovement::where('source_type', 'grn')->where('source_id', $auto->id)->first()->movement_date->format('Y-m-d'));
        $this->actingAs($this->buyer())->post("/purchase/grns/{$auto->id}/cancel", ['remarks' => 'x'])->assertSessionHasErrors('status');
        $this->actingAs($this->buyer())->put("/purchase/grns/{$auto->id}", $this->payload([$this->line($po->fresh('lines'), 1, 500), $this->line($po->fresh('lines'), 2, 300)], ['invoice_no' => 'AUTO-1']))
            ->assertSessionHasErrors('form');
    }

    // ---- list views, menu --------------------------------------------------------------------------------------------

    public function test_list_views_count_each_state(): void
    {
        $admin = $this->admin();
        $po =$this->approvedPo(null, [['item_id' => (string) $this->d['casting']->id, 'quantity' => '5000', 'rate' => ''], ['item_id' => (string) $this->d['cover']->id, 'quantity' => '3000', 'rate' => '145']]);
        $this->pendingGrn([$this->line($po, 1, 10)], ['invoice_no' => 'L-PENDING']);
        $this->matchedGrn($po, ['invoice_no' => 'L-QUALITY']);
        $ok = $this->matchedGrn($po->fresh('lines'), ['invoice_no' => 'L-APPROVED']);
        $this->actingAs($admin)->post("/purchase/grns/{$ok->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $rej = $this->matchedGrn($po->fresh('lines'), ['invoice_no' => 'L-REJECTED']);
        $this->actingAs($admin)->post("/purchase/grns/{$rej->id}/quality", ['result' => 'Rejected', 'remarks' => 'Bad'])->assertSessionHasNoErrors();
        $gone = $this->matchedGrn($po->fresh('lines'), ['invoice_no' => 'L-CANCELLED']);
        $this->actingAs($admin)->post("/purchase/grns/{$gone->id}/cancel", ['remarks' => 'x'])->assertSessionHasNoErrors();
        $del = $this->pendingGrn([$this->line($po->fresh('lines'), 1, 10)], ['invoice_no' => 'L-DELETED']);
        $this->actingAs($admin)->delete("/purchase/grns/{$del->id}")->assertSessionHasNoErrors();

        $this->assertSame([1, 1, 1], [Grn::pendingMatch()->count(), Grn::pendingQuality()->count(), Grn::qualityApproved()->count()]);
        $all = ['L-PENDING', 'L-QUALITY', 'L-APPROVED', 'L-REJECTED', 'L-CANCELLED'];
        $views = ['' => $all, 'match' => ['L-PENDING'], 'quality' => ['L-QUALITY'], 'approved' => ['L-APPROVED']];
        foreach ($views as $view => $expect) {
            $html = $this->actingAs($this->buyer())->get('/purchase/grns/list'.($view ? '?view='.$view : ''))->assertOk()->getContent();
            $this->assertStringContainsString('<span class="badge b-neu">'.count($expect).'</span>', $html, $view ?: 'all');
            foreach (array_merge($all, ['L-DELETED']) as $no) {
                in_array($no, $expect, true) ? $this->assertStringContainsString($no, $html, "$view $no") : $this->assertStringNotContainsString('>'.$no.'<', $html, "$view $no");
            }
        }
        // Search and sort on the server.
        $this->actingAs($this->buyer())->get('/purchase/grns/list?q=L-APP')->assertOk()->assertSee('L-APPROVED')->assertDontSee('L-QUALITY');
        $this->actingAs($this->buyer())->get('/purchase/grns/list?sort=amount&dir=asc')->assertOk();
        $this->actingAs($this->buyer())->get('/purchase/grns/list?sort=no&dir=desc')->assertOk();
    }

    public function test_old_menu_address_redirects_to_the_built_page(): void
    {
        $this->actingAs($this->buyer())->get('/app/grn')->assertRedirect(route('purchase.grns.index'));
        $this->actingAs($this->admin())->get('/app/grn')->assertRedirect(route('purchase.grns.index'));
        $this->actingAs($this->buyer())->get(route('purchase.grns.index'))->assertOk()->assertSee('GRN entry');
    }

    public function test_pages_render_for_every_state_and_the_csrf_token_is_on_every_form(): void
    {
        $admin = $this->admin();
        $po = $this->approvedPo();
        $pending = $this->pendingGrn([$this->line($po, 1, 10)], ['invoice_no' => 'R-1']);
        $grn = $this->matchedGrn($po);
        foreach ([$pending, $grn] as $g) {
            $html = $this->actingAs($admin)->get("/purchase/grns/{$g->id}")->assertOk()->getContent();
            preg_match_all('#<form[^>]*method="POST"[^>]*>#', $html, $forms);
            $this->assertNotEmpty($forms[0]);
            $this->assertSame(substr_count($html, 'name="_token"') >= count($forms[0]), true, 'every POST form has its CSRF token');
            $this->assertStringContainsString('class="btn add sm"', $html, 'green Add item button');
        }
        $this->actingAs($admin)->post("/purchase/grns/{$grn->id}/quality", ['result' => 'Approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get("/purchase/grns/{$grn->id}")->assertOk()->assertSee('Accepted quantity received into stock');
        $this->actingAs($admin)->get("/purchase/grns/{$pending->id}")->assertOk()->assertSee('Delete GRN');
    }
}
