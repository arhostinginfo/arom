<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\PageSetupService;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Purchase › Stock transfer (27/10/2026): rules, approval, stock through StockLedger, cancel, screens. Test database only. */
class StockTransferTest extends TestCase
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
        // Opening stock at Unit 1.
        DB::transaction(function () {
            StockLedger::move($this->u1->id, $this->item->id, today()->format('Y-m-d'), 100, 0, 'test_opening', 1);
            StockLedger::move($this->u1->id, $this->other->id, today()->format('Y-m-d'), 50, 0, 'test_opening', 1);
        });
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role, array $unitIds): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98224'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync($unitIds);

        return $u;
    }

    private function balance(Unit $unit, Item $item): float
    {
        return (float) DB::table('stock_balances')->where('unit_id', $unit->id)->where('item_id', $item->id)->value('quantity');
    }

    private function payload(array $lines, array $over = []): array
    {
        return array_merge(['transfer_date' => today()->format('d/m/Y'), 'from_unit_id' => $this->u1->id, 'to_unit_id' => $this->u2->id,
            'source_type' => 'Purchase', 'remarks' => 'To the Bhosari nursery', 'lines' => $lines, '_form' => 'transfer'], $over);
    }

    private function draft(float $qty = 40, ?User $as = null): StockTransfer
    {
        $this->actingAs($as ?? $this->admin())->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => (string) $qty, 'remarks' => '']]))
            ->assertSessionHasNoErrors();

        return StockTransfer::latest('id')->first();
    }

    public function test_save_checks_units_and_stock_and_numbers_a_draft_that_moves_nothing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '10']], ['to_unit_id' => $this->u1->id]))
            ->assertSessionHasErrors(['to_unit_id' => 'Location from and to are the same.']);
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '101']]))
            ->assertSessionHasErrors('lines.0.quantity');
        $this->assertStringContainsStringIgnoringCase('quantity is more than the stock at the source', session('errors')->first('lines.0.quantity'));
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([]))->assertSessionHasErrors('lines');
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '5'], ['item_id' => $this->item->id, 'quantity' => '5']]))
            ->assertSessionHasErrors('lines.1.item_id');
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '5']], ['transfer_date' => today()->addDay()->format('d/m/Y')]))
            ->assertSessionHasErrors('transfer_date');
        $this->assertSame(0, StockTransfer::count());

        $t = $this->draft(40);
        $this->assertSame(StockTransfer::DRAFT, $t->status);
        $this->assertMatchesRegularExpression('#^ST/\d{2}-\d{2}/0001$#', $t->transfer_no);
        $this->assertSame('Purchase', $t->source_type);
        $line = $t->lines()->first();
        $this->assertSame(Uom::where('code', 'NOS')->value('id'), $line->uom_id);
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->count());
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));
        $this->assertTrue(ActivityLog::where('action', 'stock_transfer.created')->where('subject_id', $t->id)->exists());

        // Lines sent as one JSON field (as the page does), and the next number.
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([], ['lines_json' => json_encode([['item_id' => $this->other->id, 'quantity' => '1,0', 'remarks' => 'tray']])]))
            ->assertSessionHasNoErrors();
        $t2 = StockTransfer::latest('id')->first();
        $this->assertStringEndsWith('/0002', $t2->transfer_no);
        $this->assertSame('10.000', (string) $t2->lines()->first()->quantity);
    }

    public function test_units_must_be_ones_the_user_may_work_in_and_rights_are_checked(): void
    {
        $buyer = $this->user('rahul@company.example', 'Purchase', [$this->u1->id]);
        $this->actingAs($buyer)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '5']]))
            ->assertSessionHasErrors('to_unit_id');
        $this->actingAs($buyer)->get('/purchase/stock-transfers')->assertOk()->assertSee('Stock transfer');
        $sales = $this->user('priya@company.example', 'Sales', [$this->u1->id]);
        $this->actingAs($sales)->get('/purchase/stock-transfers')->assertForbidden();
        $this->actingAs($sales)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->item->id, 'quantity' => '5']]))->assertForbidden();

        // A Purchase user can send for approval but not approve (no Stock transfer approval right by default).
        $both = $this->user('meera@company.example', 'Purchase', [$this->u1->id, $this->u2->id]);
        $t = $this->draft(10, $both);
        $this->actingAs($both)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($both)->post("/purchase/stock-transfers/{$t->id}/approve")->assertForbidden();
        // A user of neither unit cannot open it.
        $u3 = Unit::create(['code' => 'U3', 'name' => 'Unit 3']);
        $far = $this->user('far@company.example', 'Purchase', [$u3->id]);
        $this->actingAs($far)->get("/purchase/stock-transfers/{$t->id}")->assertForbidden();
    }

    public function test_approval_moves_stock_out_of_the_source_and_into_the_destination(): void
    {
        $admin = $this->admin();
        $t = $this->draft(40);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(StockTransfer::PENDING, $t->status);
        $this->assertSame(['Submitted'], $t->approvals()->pluck('action')->all());
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));
        // Pending cannot be changed or deleted.
        $this->actingAs($admin)->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->item->id, 'quantity' => '1']]))->assertSessionHasErrors('form');
        $this->actingAs($admin)->delete("/purchase/stock-transfers/{$t->id}")->assertSessionHasErrors('status');

        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(StockTransfer::APPROVED, $t->status);
        $this->assertSame($admin->id, $t->approved_by);
        $this->assertNotNull($t->approved_at);
        $this->assertSame(60.0, $this->balance($this->u1, $this->item));
        $this->assertSame(40.0, $this->balance($this->u2, $this->item));
        $rows = StockMovement::where('source_type', 'stock_transfer')->where('source_id', $t->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([$this->u1->id, '0.000', '40.000'], [$rows[0]->unit_id, (string) $rows[0]->quantity_in, (string) $rows[0]->quantity_out]);
        $this->assertSame([$this->u2->id, '40.000', '0.000'], [$rows[1]->unit_id, (string) $rows[1]->quantity_in, (string) $rows[1]->quantity_out]);
        // Approving again does nothing more.
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/approve")->assertSessionHasErrors('status');
        $this->assertSame(60.0, $this->balance($this->u1, $this->item));
    }

    public function test_approval_checks_the_stock_again_so_two_transfers_cannot_take_more_than_the_source_holds(): void
    {
        $admin = $this->admin();
        $a = $this->draft(70);
        $b = $this->draft(70);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$a->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$b->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$a->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$b->id}/approve")->assertSessionHasErrors('status');
        $this->assertStringContainsString('more than the stock at the source', session('errors')->first('status'));
        $this->assertSame(StockTransfer::PENDING, $b->fresh()->status);
        $this->assertSame(30.0, $this->balance($this->u1, $this->item));
        $this->assertSame(70.0, $this->balance($this->u2, $this->item));
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->where('source_id', $b->id)->count());
    }

    public function test_reject_needs_a_reason_and_a_changed_rejected_transfer_is_a_draft_again(): void
    {
        $admin = $this->admin();
        $t = $this->draft(40);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/reject", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/reject", ['remarks' => 'Wrong unit'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame([StockTransfer::REJECTED, 'Wrong unit'], [$t->status, $t->status_reason]);
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));

        $this->actingAs($admin)->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->other->id, 'quantity' => '20', 'remarks' => 'seedlings']], ['source_type' => 'Credit']))
            ->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame([StockTransfer::DRAFT, null, 'Credit'], [$t->status, $t->status_reason, $t->source_type]);
        $this->assertSame([$this->other->id], $t->lines()->pluck('item_id')->all());
        // Location from cannot change after saving (its number belongs to that unit's series).
        $this->actingAs($admin)->put("/purchase/stock-transfers/{$t->id}", $this->payload([['item_id' => $this->other->id, 'quantity' => '20']], ['from_unit_id' => $this->u2->id, 'to_unit_id' => $this->u1->id]))
            ->assertSessionHasErrors('from_unit_id');
        // A draft can be deleted (soft delete, logged).
        $this->actingAs($admin)->delete("/purchase/stock-transfers/{$t->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('stock_transfers', ['id' => $t->id]);
        $this->assertTrue(ActivityLog::where('action', 'stock_transfer.deleted')->exists());
    }

    public function test_cancelling_an_approved_transfer_returns_the_stock_only_while_the_destination_still_holds_it(): void
    {
        $admin = $this->admin();
        $t = $this->draft(40);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/approve");
        // Unit 2 uses 30 of the 40 received.
        DB::transaction(fn () => StockLedger::move($this->u2->id, $this->item->id, today()->format('Y-m-d'), 0, 30, 'test_use', 1));

        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Sent by mistake'])->assertSessionHasErrors('status');
        $this->assertStringContainsString('cannot be cancelled', session('errors')->first('status'));
        $this->assertSame(StockTransfer::APPROVED, $t->fresh()->status);

        // A Purchase user (no approval right) cannot cancel an approved transfer.
        $buyer = $this->user('rahul@company.example', 'Purchase', [$this->u1->id, $this->u2->id]);
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'x'])->assertSessionHasErrors('status');

        DB::transaction(fn () => StockLedger::remove('test_use', 1));
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'Sent by mistake'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame([StockTransfer::CANCELLED, 'Sent by mistake', $admin->id], [$t->status, $t->status_reason, $t->cancelled_by]);
        $this->assertSame(100.0, $this->balance($this->u1, $this->item));
        $this->assertSame(0.0, $this->balance($this->u2, $this->item));
        $this->assertSame(0, StockMovement::where('source_type', 'stock_transfer')->count());
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/cancel", ['remarks' => 'again'])->assertSessionHasErrors('status');

        // A draft can be cancelled by a Purchase user too; nothing moves.
        $d = $this->draft(5, $buyer);
        $this->actingAs($buyer)->post("/purchase/stock-transfers/{$d->id}/cancel", ['remarks' => 'Not needed'])->assertSessionHasNoErrors();
        $this->assertSame(StockTransfer::CANCELLED, $d->fresh()->status);
    }

    public function test_without_the_approval_rule_the_transfer_is_approved_and_moved_on_submit(): void
    {
        $admin = $this->admin();
        $this->assertTrue(ApprovalRule::for('stock_transfer')->is_required);
        $this->actingAs($admin)->get('/admin/approval')->assertOk()->assertSee('Stock transfer')->assertSee('Save stock transfer rule');
        $this->actingAs($admin)->put('/admin/approval/stock-transfer', ['is_required' => '0', '_st' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse(ApprovalRule::for('stock_transfer')->fresh()->is_required);
        $this->assertTrue(ApprovalRule::for('sales_order')->is_required);

        $t = $this->draft(25);
        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit")->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(StockTransfer::APPROVED, $t->status);
        $this->assertSame(['Approved automatically'], $t->approvals()->pluck('action')->all());
        $this->assertSame(75.0, $this->balance($this->u1, $this->item));
        $this->assertSame(25.0, $this->balance($this->u2, $this->item));
    }

    public function test_screens_menu_list_pickers_and_page_setup(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/app/transfer')->assertRedirect(route('purchase.stock-transfers.index'));
        $this->actingAs($admin)->get('/purchase/stock-transfers')->assertOk()
            ->assertSee(route('purchase.stock-transfers.index'), false)->assertSee('Save stock transfer')->assertSee('Source type')
            ->assertSee('The kind of inward document the stock came from.')->assertSee('Stock is usable only after Approved');
        $t = $this->draft(40);
        $this->actingAs($admin)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->assertSee($t->transfer_no)->assertSee('Send for approval')->assertSee('ARC-0001');
        $html = $this->actingAs($admin)->get('/purchase/stock-transfers/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#class="btn add sm"[^>]*>.*?Add new#s', $html);
        $this->assertDoesNotMatchRegularExpression('/decision/i', preg_replace('/<!--.*?-->/s', '', $html));

        $this->actingAs($admin)->post("/purchase/stock-transfers/{$t->id}/submit");
        $this->actingAs($admin)->get('/purchase/stock-transfers/list')->assertOk()->assertSee($t->transfer_no);
        $this->actingAs($admin)->get('/purchase/stock-transfers/list?view=pending')->assertOk()->assertSee($t->transfer_no);
        $this->actingAs($admin)->get('/purchase/stock-transfers/list?view=approved')->assertOk()->assertDontSee($t->transfer_no);
        $this->actingAs($admin)->get('/purchase/stock-transfers/lookup?q=1')->assertOk()->assertJsonFragment(['id' => $t->id]);

        $items = $this->actingAs($admin)->getJson('/purchase/stock-transfers/items?from='.$this->u1->id.'&q=ARC')->assertOk()->json();
        $this->assertSame($this->item->id, $items[0]['id']);
        $this->assertSame(['uom' => 'NOS', 'stock' => '100'], $items[0]['data']);
        $stock = $this->actingAs($admin)->getJson('/purchase/stock-transfers/stock?unit='.$this->u1->id.'&items='.$this->item->id.','.$this->other->id)->assertOk()->json();
        $this->assertEquals([$this->item->id => 100, $this->other->id => 50], $stock);
        $this->actingAs($admin)->getJson('/purchase/stock-transfers/stock?unit=999&items='.$this->item->id)->assertOk()->assertExactJson([]);

        // Page setup: the page is listed under Purchase; a hidden Source type is not validated or saved.
        $this->actingAs($admin)->get('/admin/page-setup/stock-transfer')->assertOk()->assertSee('Save page setup');
        app(PageSetupService::class)->save('stock-transfer', ['source_type' => ['visible' => false], 'remarks' => ['visible' => true, 'required' => true]], $admin);
        $this->actingAs($admin)->post('/purchase/stock-transfers', $this->payload([['item_id' => $this->other->id, 'quantity' => '1']], ['remarks' => '', 'source_type' => 'Bogus']))
            ->assertSessionHasErrors('remarks')->assertSessionDoesntHaveErrors('source_type');
        $this->actingAs($admin)->get('/purchase/stock-transfers/create')->assertOk()->assertDontSee('The kind of inward document the stock came from.');

        // Menu: Purchase › Stock Transfer opens the real page.
        $this->actingAs($admin)->get('/dashboard')->assertSee(route('purchase.stock-transfers.index'), false);
    }
}
