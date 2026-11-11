<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Currency;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\StockLedger;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Purchase › Stock transfer: tester's review coverage (27/10/2026). Stock ledger consistency, approval, cancel against
 * a sales invoice, unit and approval rights on every action, edit/delete rules, list views, old route and menu.
 * Test database only (sqlite :memory:).
 */
class StockTransferReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Unit $u1;

    private Unit $u2;

    private Item $item;

    private Item $other;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->u1, $this->u2] = Unit::orderBy('id')->get()->all();
        $tax = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '0602', 'type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $tax->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn ($no) => Item::create(['description' => 'Plant '.$no, 'item_no' => $no, 'type' => 'Both', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => 100, 'status' => 'Active']);
        $this->item = $mk('ARC-0001');
        $this->other = $mk('FCS-0002');
        DB::transaction(function () {
            StockLedger::move($this->u1->id, $this->item->id, today()->format('Y-m-d'), 100, 0, 'test_opening', 1);
            StockLedger::move($this->u1->id, $this->other->id, today()->format('Y-m-d'), 50, 0, 'test_opening', 1);
        });
    }

    // ---- helpers ----

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string|Role $role, array $unitIds): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98225'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync($unitIds);

        return $u;
    }

    /** A role with only the Stock transfer approval right (and viewing transfers). */
    private function approverRole(bool $withView = true): Role
    {
        $role = Role::create(['name' => 'ST Approver'.($withView ? '' : ' only'), 'guard_name' => 'web']);
        $role->givePermissionTo(array_merge(['stock-transfer-approval.view', 'stock-transfer-approval.edit'], $withView ? ['stock-transfer.view'] : []));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    private function balance(Unit $unit, Item $item): float
    {
        return (float) DB::table('stock_balances')->where('unit_id', $unit->id)->where('item_id', $item->id)->value('quantity');
    }

    /** Every stock_balances row equals the sum of its stock_movements. */
    private function assertLedgerConsistent(): void
    {
        $sums = DB::table('stock_movements')->groupBy('unit_id', 'item_id')
            ->selectRaw('unit_id, item_id, SUM(quantity_in - quantity_out) AS q')->get()
            ->mapWithKeys(fn ($r) => [$r->unit_id.':'.$r->item_id => round((float) $r->q, 3)]);
        $bal = DB::table('stock_balances')->get()->mapWithKeys(fn ($r) => [$r->unit_id.':'.$r->item_id => round((float) $r->quantity, 3)]);
        foreach ($bal->keys()->merge($sums->keys())->unique() as $k) {
            $this->assertEquals($sums[$k] ?? 0.0, $bal[$k] ?? 0.0, "stock_balances $k does not match stock_movements");
        }
    }

    private function payload(array $lines, array $over = []): array
    {
        return array_merge(['transfer_date' => today()->format('d/m/Y'), 'from_unit_id' => $this->u1->id, 'to_unit_id' => $this->u2->id,
            'source_type' => 'Purchase', 'remarks' => 'Review', 'lines' => $lines, '_form' => 'transfer'], $over);
    }

    private function draft(array $lines = null, ?User $as = null, array $over = []): StockTransfer
    {
        $lines ??= [['item_id' => $this->item->id, 'quantity' => '40']];
        $this->actingAs($as ?? $this->admin())->post('/purchase/stock-transfers', $this->payload($lines, $over))->assertSessionHasNoErrors();

        return StockTransfer::latest('id')->first();
    }

    private function pending(array $lines = null): StockTransfer
    {
        $t = $this->draft($lines);
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();

        return $t->fresh();
    }

    private function approved(array $lines = null): StockTransfer
    {
        $t = $this->pending($lines);
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();

        return $t->fresh();
    }

    // ---- stock ----

    public function test_approve_moves_stock_writes_two_movements_per_item_and_keeps_balances_equal_to_the_ledger(): void
    {
        $t = $this->pending([['item_id' => $this->item->id, 'quantity' => '40'], ['item_id' => $this->other->id, 'quantity' => '12.5']]);
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->count());

        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/approve", ['remarks' => 'ok'])->assertSessionHasNoErrors();
        $rows = StockMovement::where('source_type', 'stock_transfer')->where('source_id', $t->id)->get();
        $this->assertCount(4, $rows);
        $this->assertCount(2, $rows->where('item_id', $this->item->id));
        $this->assertEquals(40.0, (float) $rows->where('unit_id', $this->u1->id)->where('item_id', $this->item->id)->first()->quantity_out);
        $this->assertEquals(40.0, (float) $rows->where('unit_id', $this->u2->id)->where('item_id', $this->item->id)->first()->quantity_in);
        $this->assertSame(today()->format('Y-m-d'), $rows->first()->movement_date instanceof \DateTimeInterface ? $rows->first()->movement_date->format('Y-m-d') : substr((string) $rows->first()->movement_date, 0, 10));
        $this->assertSame([60.0, 40.0, 37.5, 12.5], [$this->balance($this->u1, $this->item), $this->balance($this->u2, $this->item), $this->balance($this->u1, $this->other), $this->balance($this->u2, $this->other)]);
        $this->assertLedgerConsistent();

        // Logged with the approval remarks in the approval history.
        $this->assertTrue(ActivityLog::where('action', 'stock_transfer.status_changed')->where('subject_id', $t->id)->where('description', 'like', 'Approved%stock moved%')->exists());
        $this->assertSame(['Submitted', 'Approved'], $t->approvals()->orderBy('id')->pluck('action')->all());
        $this->assertSame('ok', $t->approvals()->where('action', 'Approved')->value('remarks'));
    }

    public function test_second_approval_exceeding_the_remaining_stock_is_refused_and_moves_nothing(): void
    {
        $a = $this->pending([['item_id' => $this->item->id, 'quantity' => '60']]);
        // b: one item fits, the other exceeds what will be left after a; all or nothing.
        $b = $this->pending([['item_id' => $this->other->id, 'quantity' => '10'], ['item_id' => $this->item->id, 'quantity' => '41']]);
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$a->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$b->id}/approve")->assertSessionHasErrors('status');
        $this->assertStringContainsString('ARC-0001', session('errors')->first('status'));
        $this->assertSame(StockTransfer::PENDING, $b->fresh()->status);
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $b->id)->count());
        $this->assertSame(50.0, $this->balance($this->u1, $this->other));
        $this->assertSame(40.0, $this->balance($this->u1, $this->item));
        $this->assertLedgerConsistent();

        // Exactly the remaining 40 can still go.
        $c = $this->pending([['item_id' => $this->item->id, 'quantity' => '40']]);
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$c->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance($this->u1, $this->item));
    }

    public function test_submit_refuses_when_the_source_stock_fell_below_the_draft_quantity(): void
    {
        $t = $this->draft([['item_id' => $this->item->id, 'quantity' => '90']]);
        DB::transaction(fn () => StockLedger::move($this->u1->id, $this->item->id, today()->format('Y-m-d'), 0, 20, 'test_use', 1));
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasErrors('status');
        $this->assertSame(StockTransfer::DRAFT, $t->fresh()->status);
    }

    public function test_cancel_of_an_approved_transfer_reverses_the_stock_and_is_logged(): void
    {
        $t = $this->approved([['item_id' => $this->item->id, 'quantity' => '40'], ['item_id' => $this->other->id, 'quantity' => '5']]);
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Wrong plant'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(StockTransfer::CANCELLED, $t->status);
        $this->assertNotNull($t->cancelled_at);
        $this->assertSame([100.0, 0.0, 50.0, 0.0], [$this->balance($this->u1, $this->item), $this->balance($this->u2, $this->item), $this->balance($this->u1, $this->other), $this->balance($this->u2, $this->other)]);
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $t->id)->count());
        $this->assertLedgerConsistent();
        $this->assertTrue(ActivityLog::where('action', 'stock_transfer.status_changed')->where('subject_id', $t->id)->where('description', 'like', 'Cancelled%returned the stock%Wrong plant')->exists());
        // A cancelled transfer cannot be approved, edited, deleted or submitted.
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->delete("/purchase/stock-transfers/{$t->id}")->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '1']]))->assertSessionHasErrors('form');
    }

    public function test_cancel_is_refused_when_a_sales_invoice_of_the_destination_used_the_stock(): void
    {
        $t = $this->approved([['item_id' => $this->item->id, 'quantity' => '40']]);

        // A tax invoice raised at Unit 2 (the destination) takes 30 of the 40 received.
        Company::create(['name' => 'Green Nursery Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Pune']);
        $party = Party::create(['name' => 'Garden World', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Pune', 'shipping_address' => 'Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $this->actingAs($this->admin())->withSession(['unit_id' => $this->u2->id])->post('/sales/invoices', [
            'invoice_type' => 'Sales', 'invoice_date' => today()->format('d/m/Y'), 'party_id' => $party->id, 'sales_order_id' => '', 'shipping_address' => 'Pune',
            'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => 'MH 14 AB 1234', 'distance_km' => '10', 'pf_charges' => '',
            'lines' => [['item_id' => $this->item->id, 'quantity' => '30', 'rate' => '100']],
        ])->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->first();
        $this->assertSame($this->u2->id, $inv->unit_id);
        $this->assertSame(10.0, $this->balance($this->u2, $this->item));

        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Sent by mistake'])->assertSessionHasErrors('status');
        $msg = session('errors')->first('status');
        $this->assertStringContainsString('ARC-0001', $msg);
        $this->assertStringContainsString('cannot be cancelled', $msg);
        $this->assertSame(StockTransfer::APPROVED, $t->fresh()->status);
        $this->assertSame(60.0, $this->balance($this->u1, $this->item));
        $this->assertSame(2, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $t->id)->count());
        $this->assertLedgerConsistent();

        // Once the invoice is cancelled the transfer can be cancelled.
        $this->actingAs($this->admin())->withSession(['unit_id' => $this->u2->id])->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'Raised in error'])->assertSessionHasNoErrors();
        $this->assertSame(40.0, $this->balance($this->u2, $this->item));
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Sent by mistake'])->assertSessionHasNoErrors();
        $this->assertSame([100.0, 0.0], [$this->balance($this->u1, $this->item), $this->balance($this->u2, $this->item)]);
        $this->assertLedgerConsistent();
    }

    // ---- rights ----

    public function test_a_user_without_access_to_the_units_is_refused_on_every_action(): void
    {
        $t = $this->draft();
        $u3 = Unit::create(['code' => 'U3', 'name' => 'Unit 3']);
        $far = $this->user('far@company.example', 'Purchase', [$u3->id]);
        $far->assignRole($this->approverRole());

        $this->actingAs($far)->get("/purchase/stock-transfers/{$t->id}")->assertForbidden();
        // Update: the form request runs before the controller's unit check, so this is a validation refusal (units not allowed).
        $this->actingAs($far)->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '1']]))->assertSessionHasErrors(['from_unit_id', 'to_unit_id']);
        $this->assertSame('40.000', (string) $t->lines()->first()->quantity);
        $this->actingAs($far)->post("/purchase/stock-transfers/{$t->id}/submit")->assertForbidden();
        $this->actingAs($far)->delete("/purchase/stock-transfers/{$t->id}")->assertForbidden();
        $this->actingAs($far)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'x'])->assertForbidden();
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->actingAs($far)->post("/purchase/stock-transfers/{$t->id}/approve")->assertForbidden();
        $this->actingAs($far)->post("/purchase/stock-transfers/{$t->id}/reject", ['remarks' => 'x'])->assertForbidden();
        $this->assertSame(StockTransfer::PENDING, $t->fresh()->status);

        // Creating with units the user may not work in is refused by validation.
        $this->actingAs($far)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '1']]))
            ->assertSessionHasErrors(['from_unit_id', 'to_unit_id']);
        // Pickers do not reveal the stock of a unit the user may not work in; the list and lookup do not show the transfer.
        $items = $this->actingAs($far)->getJson('/purchase/stock-transfers/items?from='.$this->u1->id.'&q=ARC')->assertOk()->json();
        $this->assertSame('', $items[0]['data']['stock']);
        $this->actingAs($far)->getJson('/purchase/stock-transfers/stock?unit='.$this->u1->id.'&items='.$this->item->id)->assertOk()->assertExactJson([]);
        $this->actingAs($far)->get('/purchase/stock-transfers/list')->assertOk()->assertDontSee($t->transfer_no);
        $this->actingAs($far)->getJson('/purchase/stock-transfers/lookup?q=1')->assertOk()->assertJsonMissing(['id' => $t->id]);
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));
    }

    public function test_only_users_with_the_approval_right_can_approve_or_reject(): void
    {
        $buyer = $this->user('rahul@company.example', 'Purchase', [$this->u1->id, $this->u2->id]);
        $t = $this->draft(null, $buyer);
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::PENDING, $t->fresh()->status);

        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$t->id}/approve")->assertForbidden();
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$t->id}/reject", ['remarks' => 'no'])->assertForbidden();
        $sales = $this->user('priya@company.example', 'Sales', [$this->u1->id, $this->u2->id]);
        $this->actingAs($sales)->post("/purchase/stock-transfers/{$t->id}/approve")->assertForbidden();
        // The page does not offer Approve to the buyer.
        $this->actingAs($buyer)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->assertSee('Log in as an approver to approve.')
            ->assertDontSee(route('purchase.stock-transfers.approve', $t), false);
        $this->assertSame(StockTransfer::PENDING, $t->fresh()->status);
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));

        // A user given only the Stock transfer approval right approves.
        $approver = $this->user('anil@company.example', $this->approverRole(), [$this->u1->id, $this->u2->id]);
        $this->actingAs($approver)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->assertSee(route('purchase.stock-transfers.approve', $t), false);
        $this->actingAs($approver)->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame([StockTransfer::APPROVED, $approver->id], [$t->status, $t->approved_by]);
        $this->assertSame($buyer->id, $t->created_by);
        // But cannot add or edit transfers (no Stock transfer · Add / Edit).
        $this->actingAs($approver)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '1']]))->assertForbidden();
    }

    public function test_editing_a_pending_or_approved_transfer_is_refused(): void
    {
        $p = $this->pending([['item_id' => $this->item->id, 'quantity' => '10']]);
        $this->actingAs($this->admin())->put("/purchase/stock-transfers/{$p->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '99']]))
            ->assertSessionHasErrors('form');
        $this->assertSame('10.000', (string) $p->lines()->first()->quantity);
        $this->actingAs($this->admin())->get("/purchase/stock-transfers/{$p->id}")->assertOk()->assertSee('so it cannot be changed')->assertDontSee('Save changes');

        $a = $this->approved([['item_id' => $this->item->id, 'quantity' => '20']]);
        $this->actingAs($this->admin())->put("/purchase/stock-transfers/{$a->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '1']], ['to_unit_id' => $this->u2->id]))
            ->assertSessionHasErrors('form');
        $this->assertSame('20.000', (string) $a->lines()->first()->quantity);
        $this->assertSame(StockTransfer::APPROVED, $a->fresh()->status);
        $this->actingAs($this->admin())->get("/purchase/stock-transfers/{$a->id}")->assertOk()->assertDontSee('Save changes')->assertSee('The stock has moved');
    }

    public function test_only_a_draft_or_rejected_transfer_can_be_deleted(): void
    {
        $admin = $this->admin();
        $d = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']]);
        $r = $this->pending([['item_id' => $this->item->id, 'quantity' => '2']]);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$r->id}/reject", ['remarks' => 'No'])->assertSessionHasNoErrors();
        $p = $this->pending([['item_id' => $this->item->id, 'quantity' => '3']]);
        $a = $this->approved([['item_id' => $this->item->id, 'quantity' => '4']]);
        $c = $this->draft([['item_id' => $this->item->id, 'quantity' => '5']]);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$c->id}/cancel", ['remarks' => 'No'])->assertSessionHasNoErrors();

        // No delete right: refused before anything else.
        $sales = $this->user('priya@company.example', 'Sales', [$this->u1->id, $this->u2->id]);
        $this->actingAs($sales)->delete("/purchase/stock-transfers/{$d->id}")->assertForbidden();

        foreach ([$p, $a, $c] as $t) {
            $this->actingAs($admin)->delete("/purchase/stock-transfers/{$t->id}")->assertSessionHasErrors('status');
            $this->assertNotSoftDeleted('stock_transfers', ['id' => $t->id]);
        }
        foreach ([$d, $r] as $t) {
            $this->actingAs($admin)->delete("/purchase/stock-transfers/{$t->id}")->assertRedirect(route('purchase.stock-transfers.index'))->assertSessionHasNoErrors();
            $this->assertSoftDeleted('stock_transfers', ['id' => $t->id]);
            $this->actingAs($admin)->get("/purchase/stock-transfers/{$t->id}")->assertNotFound();
        }
        $this->assertSame(96.0, $this->balance($this->u1, $this->item));
        $this->assertLedgerConsistent();
        // The deleted numbers stay used: the next transfer takes a new number.
        $n = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']]);
        $this->assertStringEndsWith('/0006', $n->transfer_no);
    }

    public function test_with_the_rule_off_submit_approves_at_once_but_still_checks_stock(): void
    {
        ApprovalRule::where('document_type', 'stock_transfer')->update(['is_required' => false]);
        $buyer = $this->user('rahul@company.example', 'Purchase', [$this->u1->id, $this->u2->id]);
        $t = $this->draft([['item_id' => $this->item->id, 'quantity' => '30']], $buyer);
        $this->actingAs($buyer)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->assertSee('Approve and move stock');
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame([StockTransfer::APPROVED, $buyer->id], [$t->status, $t->approved_by]);
        $this->assertSame([70.0, 30.0], [$this->balance($this->u1, $this->item), $this->balance($this->u2, $this->item)]);
        $this->assertSame(2, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $t->id)->count());

        // A draft for more than is left is refused on submit and stays a Draft.
        $big = $this->draft([['item_id' => $this->item->id, 'quantity' => '70']], $buyer);
        DB::transaction(fn () => StockLedger::move($this->u1->id, $this->item->id, today()->format('Y-m-d'), 0, 1, 'test_use', 1));
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$big->id}/submit")->assertSessionHasErrors('status');
        $this->assertSame(StockTransfer::DRAFT, $big->fresh()->status);
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $big->id)->count());
        $this->assertLedgerConsistent();
    }

    public function test_numbers_follow_the_source_unit_series(): void
    {
        $a = $this->draft([['item_id' => $this->item->id, 'quantity' => '40']]);
        $this->approved([['item_id' => $this->item->id, 'quantity' => '10']]);
        // Unit 2 now holds 10 and starts its own series.
        $b = $this->draft([['item_id' => $this->item->id, 'quantity' => '5']], null, ['from_unit_id' => $this->u2->id, 'to_unit_id' => $this->u1->id]);
        $this->assertStringEndsWith('/0001', $a->transfer_no);
        $this->assertStringEndsWith('/0001', $b->transfer_no);
        $this->assertSame($a->financial_year_id, $b->financial_year_id);
        $c = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']]);
        $this->assertStringEndsWith('/0003', $c->transfer_no);
        // A date outside the financial year is refused.
        $this->actingAs($this->admin())->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '1']], ['transfer_date' => '01/01/2020']))
            ->assertSessionHasErrors('transfer_date');
    }

    // ---- list, route, menu, page ----

    public function test_list_views_counts_search_and_sort(): void
    {
        $admin = $this->admin();
        $d = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']]);
        $p = $this->pending([['item_id' => $this->item->id, 'quantity' => '2']]);
        $a = $this->approved([['item_id' => $this->item->id, 'quantity' => '3'], ['item_id' => $this->other->id, 'quantity' => '1']]);
        // Into Unit 1 from Unit 2 (Unit 1 is the destination): still listed for Unit 1.
        $back = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']], null, ['from_unit_id' => $this->u2->id, 'to_unit_id' => $this->u1->id]);
        // Between Unit 2 and Unit 3: not listed for Unit 1.
        $u3 = Unit::create(['code' => 'U3', 'name' => 'Unit 3']);
        $admin->units()->attach($u3->id);
        $admin->unsetRelation('units');
        $away = $this->draft([['item_id' => $this->item->id, 'quantity' => '1']], $admin, ['from_unit_id' => $this->u2->id, 'to_unit_id' => $u3->id]);

        $total = fn ($url) => $this->actingAs($admin)->withSession(['unit_id' => $this->u1->id])->get($url)->assertOk()->viewData('rows')->total();
        $this->assertSame(4, $total('/purchase/stock-transfers/list'));
        $this->assertSame(1, $total('/purchase/stock-transfers/list?view=pending'));
        $this->assertSame(1, $total('/purchase/stock-transfers/list?view=approved'));
        $this->assertSame(4, $total('/purchase/stock-transfers/list?view=bogus'));
        $this->actingAs($admin)->withSession(['unit_id' => $this->u1->id])->get('/purchase/stock-transfers/list')->assertOk()
            ->assertSee($back->transfer_no)->assertViewHas('rows', fn ($r) => ! collect($r->items())->contains('id', $away->id));
        // Unit 3 on the top bar sees only the transfer it is part of.
        $u3rows = $this->actingAs($admin)->withSession(['unit_id' => $u3->id])->get('/purchase/stock-transfers/list')->assertOk()->viewData('rows');
        $this->assertSame([$away->id], collect($u3rows->items())->pluck('id')->all());

        // Search by number (full or just the digits) and the item count column.
        $rows = $this->actingAs($admin)->withSession(['unit_id' => $this->u1->id])->get('/purchase/stock-transfers/list?q='.urlencode($a->transfer_no))->viewData('rows');
        $this->assertSame([$a->id], collect($rows->items())->pluck('id')->all());
        $this->assertSame(2, $rows->items()[0]->lines_count);
        $this->assertGreaterThanOrEqual(1, $total('/purchase/stock-transfers/list?q=3'));
        $this->assertSame(0, $total('/purchase/stock-transfers/list?q=ZZZ'));
        // Sort by number ascending; a bad sort column falls back to the default.
        $asc = $this->actingAs($admin)->withSession(['unit_id' => $this->u1->id])->get('/purchase/stock-transfers/list?sort=no&dir=asc')->viewData('rows');
        $nos = collect($asc->items())->pluck('transfer_no')->all();
        $sorted = $nos;
        sort($sorted);
        $this->assertSame($sorted, $nos);
        $this->actingAs($admin)->withSession(['unit_id' => $this->u1->id])->get('/purchase/stock-transfers/list?sort=drop table&dir=sideways')->assertOk();
        // Phone cards: each cell carries its label; the add button is the green one.
        $this->actingAs($admin)->get('/purchase/stock-transfers/list')->assertSee('data-label="Transfer no"', false)->assertSee('class="btn add"', false);
        $this->assertNotNull($d->id);
        $this->assertNotNull($p->id);
    }

    public function test_old_prototype_address_redirects_and_the_menu_shows_the_real_link(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/app/transfer')->assertRedirect(route('purchase.stock-transfers.index'));
        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('purchase.stock-transfers.index').'"', $html);
        $this->assertStringNotContainsString(url('/app/transfer').'"', $html);
        // A role without Stock transfer · View does not get the menu link or the page.
        $sales = $this->user('priya@company.example', 'Sales', [$this->u1->id]);
        $this->assertStringNotContainsString(route('purchase.stock-transfers.index').'"', $this->actingAs($sales)->get('/dashboard')->getContent());
        $this->actingAs($sales)->get('/purchase/stock-transfers/list')->assertForbidden();
    }

    public function test_page_forms_carry_csrf_tokens_and_the_activity_log_records_each_step(): void
    {
        $t = $this->draft([['item_id' => $this->item->id, 'quantity' => '10']]);
        $html = $this->actingAs($this->admin())->get("/purchase/stock-transfers/{$t->id}")->assertOk()->getContent();
        preg_match_all('#<form[^>]*method="POST"[^>]*>(.*?)</form>#s', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $body) {
            $this->assertStringContainsString('name="_token"', $body);
        }
        $this->actingAs($this->admin())->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '12']]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/reject", ['remarks' => 'Fix qty']);
        $actions = ActivityLog::where('subject_id', $t->id)->where('action', 'like', 'stock_transfer.%')->orderBy('id')->pluck('action')->all();
        $this->assertSame(['stock_transfer.created', 'stock_transfer.updated', 'stock_transfer.status_changed', 'stock_transfer.status_changed'], $actions);
        $this->actingAs($this->admin())->get("/purchase/stock-transfers/{$t->id}")->assertOk()->assertSee('Reason: Fix qty')->assertSee('Approval history');
    }

    // ---- review findings (expected behaviour; these fail until the bugs are fixed) ----

    /**
     * BUG: a user of the destination unit only can send a transfer of a source unit they may not work in, and with the
     * approval rule off that moves stock out of the source (controller authorizeUnit accepts either unit).
     */
    public function test_bug_a_destination_only_user_cannot_move_stock_out_of_a_source_they_may_not_work_in(): void
    {
        ApprovalRule::where('document_type', 'stock_transfer')->update(['is_required' => false]);
        $t = $this->draft([['item_id' => $this->item->id, 'quantity' => '30']]);
        $dest = $this->user('dest@company.example', 'Purchase', [$this->u2->id]);
        $res = $this->actingAs($dest)->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->assertTrue($res->status() === 403 || session('errors')?->has('status'), 'A user without access to the source unit sent the transfer.');
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));
    }

    /**
     * BUG: the "only an approver can cancel an Approved transfer" check is in the controller, before the row lock; the
     * service does not check it, so a cancel that races an approval reverses stock for a user without the right.
     * Shown by calling the service as the controller would after its (stale) check passed on a Pending transfer.
     */
    public function test_bug_cancel_of_an_approved_transfer_checks_the_approval_right_under_the_lock(): void
    {
        $buyer = $this->user('rahul@company.example', 'Purchase', [$this->u1->id, $this->u2->id]);
        $t = $this->pending([['item_id' => $this->item->id, 'quantity' => '40']]);
        $stale = $t->fresh(); // the controller's copy: Pending, so its approver check passes
        $this->actingAs($this->admin())->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($buyer);
        try {
            app(StockTransferService::class)->cancel($stale, $buyer, 'race');
        } catch (ValidationException) {
        }
        $this->assertSame(StockTransfer::APPROVED, $t->fresh()->status, 'A user without Stock transfer approval · Edit cancelled an Approved transfer.');
        $this->assertSame(40.0, $this->balance($this->u2, $this->item));
    }
}
