<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\FinancialYear;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Create Sales Order from a customer PO (02/11/2026): the button on CRM › 6 PO / Agreement, its disabled states (no items,
 * a prospect's PO with no Party), the Draft sales order it makes (customer, PO no, dates, items, link both ways, logs),
 * the sales order rules (PO no per party, sales items), one active order per PO, units, and the order reaching the Tax
 * invoice "PO no" list once approved.
 * Separate PO menu removed (02/11/2026): the PO menu's route for it is gone; every case uses the CRM page.
 */
class PoToSalesOrderTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** PO rights only: no Sales order · Add. */
    private function poClerk(): User
    {
        Role::findOrCreate('PO clerk', 'web')->syncPermissions(['dashboard.view', 'customer-po.view', 'customer-po.add', 'customer-po.edit']);
        $u = User::create(['name' => 'Clerk', 'email' => 'clerk@company.example', 'mobile' => '9822000031', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('PO clerk');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function customer(string $name = 'Test Party'): Party
    {
        return Party::create(['name' => $name, 'type' => 'Customer', 'billing_address' => 'Pune', 'shipping_address' => 'Gate 1, Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
    }

    private function item(?Party $party, string $no, float $price): Item
    {
        $gst = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::firstOrCreate(['code' => '0602'], ['type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $gst->id, 'is_active' => true]);

        return Item::create(['party_id' => $party?->id, 'description' => 'Plant '.$no, 'item_no' => $no, 'type' => 'Sales', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => Uom::where('code', 'NOS')->value('id'), 'tax_structure_id' => $gst->id, 'price' => $price, 'currency_id' => Currency::defaultId(), 'status' => 'Active']);
    }

    private function line(Item $i, $qty, $rate = ''): array
    {
        return ['item_id' => (string) $i->id, 'description' => '', 'quantity' => (string) $qty, 'uom_id' => '', 'rate' => (string) $rate];
    }

    /** A sent quotation from a new enquiry of $party (a prospect's when null). */
    private function quotation(?Party $party): Quotation
    {
        $e = $this->crmEnquiry($party ? ['party_id' => $party->id, 'prospect_id' => ''] : []);
        $this->actingAs($this->admin())->post(route('crm.quotations.store'), $this->newQuotationPayload(['enquiry_id' => $e->id]))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('crm.quotations.submit', $q));
        $this->actingAs($this->admin())->post(route('crm.quotations.sent', $q));

        return $q->refresh();
    }

    /** A PO saved on CRM › 6 PO / Agreement by the Sales user, against a new sent quotation of $party. */
    private function crmPo(?Party $party, array $lines, array $over = []): CustomerPo
    {
        $q = $this->quotation($party);
        $this->actingAs($this->sales())->post(route('crm.customer-pos.store'), array_merge(['quotation_id' => $q->id, 'po_no' => '21312312', 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026', 'lines_json' => json_encode($lines)], $over))->assertSessionHasNoErrors();

        return CustomerPo::latest('id')->firstOrFail();
    }

    public function test_button_shows_for_sales_order_add_only_and_is_disabled_without_items_or_party(): void
    {
        $party = $this->customer();
        $rose = $this->item($party, 'ROSE-1', 40);
        $sales = $this->sales();

        $po = $this->crmPo($party, [$this->line($rose, 10, 45)]);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('Create Sales Order')->assertSee(route('crm.customer-pos.sales-order', $po), false)->assertDontSee('poSoWhy');

        // No items: disabled, with the reason; posting it anyway is refused.
        $bare = $this->crmPo($party, [], ['po_no' => 'NO-ITEMS']);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $bare))->assertOk()
            ->assertSee('Create Sales Order')->assertSee('To create a sales order: Add items to the PO first.')->assertSee('id="poMakeSo" disabled', false);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $bare))->assertRedirect(route('crm.customer-pos.show', $bare))
            ->assertSessionHasErrors(['sales_order' => 'Add items to the PO first.']);
        $this->assertSame(0, SalesOrder::count());

        // No Sales order · Add: no button, and the route is forbidden.
        $clerk = $this->poClerk();
        $this->actingAs($clerk)->get(route('crm.customer-pos.show', $po))->assertOk()->assertDontSee('Create Sales Order');
        $this->actingAs($clerk)->post(route('crm.customer-pos.sales-order', $po))->assertForbidden();
        $this->assertSame(0, SalesOrder::count());

        // A CRM PO for a prospect (no Party): disabled with the hint.
        $prospectPo = $this->crmPo(null, [$this->line($rose, 5)], ['po_no' => 'PR/1']);
        $this->assertNull($prospectPo->party_id);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $prospectPo))->assertOk()
            ->assertSee('Add this client to the Party master first')->assertSee('id="poMakeSo" disabled', false);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $prospectPo))
            ->assertSessionHasErrors('sales_order');
        $this->assertSame(0, SalesOrder::count());

        // Once made: "Open sales order" and the order's number and status instead of the button.
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $so = SalesOrder::firstOrFail();
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('Open sales order')->assertSee(route('sales.orders.show', $so), false)->assertSee($so->so_no)->assertSee('Draft, made from this PO.', false)
            ->assertDontSee('Create Sales Order');
        // The clerk sees the order's number but cannot open it.
        $this->actingAs($clerk)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee($so->so_no)->assertDontSee('Open sales order')
            ->assertDontSee(route('sales.orders.show', $so), false);
    }

    public function test_order_copies_customer_po_no_dates_and_items_as_a_draft_with_links_and_logs(): void
    {
        $party = $this->customer();
        $rose = $this->item($party, 'ROSE-1', 40);
        $fern = $this->item(null, 'FERN-2', 12.5);
        $sales = $this->sales();
        $po = $this->crmPo($party, [$this->line($rose, 10, 45), $this->line($fern, '2.5', '')],
            ['valid_from' => '10/10/2026', 'valid_to' => '31/03/2027']);

        $res = $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $so = SalesOrder::with('lines')->firstOrFail();
        $res->assertRedirect(route('sales.orders.show', $so))
            ->assertSessionHas('toast', 'Sales order '.$so->so_no.' made from PO 21312312. Check it and send it for approval.');

        $this->assertSame([$party->id, '21312312', '2026-10-10', '2027-03-31', null, null, 'Draft', $po->id, $po->unit_id],
            [$so->party_id, $so->po_number, $so->start_date->toDateString(), $so->end_date->toDateString(), $so->revision_no, $so->revision_date, $so->status, $so->customer_po_id, $so->unit_id]);
        $this->assertSame('SO/'.FinancialYear::forDate(now())->code.'/0001', $so->so_no);
        $this->assertSame([$rose->id, $fern->id], $so->lines->pluck('item_id')->all());
        $this->assertSame(['10.000', '2.500'], $so->lines->pluck('quantity')->all());
        $this->assertSame(['45.0000', '12.5000'], $so->lines->pluck('rate')->all());
        $this->assertSame(['2027-03-31', '2027-03-31'], $so->lines->map(fn ($l) => \Illuminate\Support\Carbon::parse($l->delivery_date)->toDateString())->all());
        $this->assertSame('481.25', $so->total_value);
        $this->assertSame($so->id, $po->fresh()->salesOrder->id);

        // Logs on both records.
        $this->assertTrue(ActivityLog::where('action', 'sales_order.created')->where('subject_id', $so->id)->exists());
        $log = ActivityLog::where('action', 'customer_po.sales_order_made')->where('subject_id', $po->id)->where('subject_type', $po->getMorphClass())->firstOrFail();
        $this->assertStringContainsString($so->so_no, $log->description);

        // The order shows "From PO" linked to the CRM PO page.
        $this->actingAs($sales)->get(route('sales.orders.show', $so))->assertOk()->assertSee('From PO')->assertSee(route('crm.customer-pos.show', $po), false);

        // No end date on the PO: the order has none, and each delivery date is the PO date.
        $po2 = $this->crmPo($party, [$this->line($rose, 3, 45)], ['po_no' => 'PO-2']);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po2))->assertSessionHasNoErrors();
        $so2 = SalesOrder::latest('id')->with('lines')->first();
        $this->assertNull($so2->end_date);
        $this->assertSame('2026-10-10', \Illuminate\Support\Carbon::parse($so2->lines[0]->delivery_date)->toDateString());

        // The user can then send it for approval as usual.
        $this->actingAs($sales)->post(route('sales.orders.submit', $so))->assertSessionHasNoErrors();
        $this->assertSame('Pending approval', $so->fresh()->status);
    }

    public function test_sales_order_rules_apply_and_only_one_active_order_per_po(): void
    {
        $party = $this->customer();
        $rose = $this->item($party, 'ROSE-1', 40);
        $sales = $this->sales();
        $po = $this->crmPo($party, [$this->line($rose, 10, 45)]);

        // S-5: the PO no is already on an order of the party (typed in Sales): the order's own message.
        $this->actingAs($sales)->post('/sales/orders', ['party_id' => $party->id, 'po_number' => '21312312', 'start_date' => '10/10/2026', 'end_date' => '',
            'revision_no' => '0', 'revision_date' => '', 'lines' => [['item_id' => $rose->id, 'quantity' => '1', 'rate' => '', 'delivery_date' => '10/10/2026']]])->assertSessionHasNoErrors();
        $typed = SalesOrder::firstOrFail();
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertRedirect(route('crm.customer-pos.show', $po))
            ->assertSessionHasErrors(['sales_order' => 'This PO number is already on another sales order of this party.']);
        $this->assertSame(1, SalesOrder::count());
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('The sales order was not made.');
        $this->actingAs($sales)->delete(route('sales.orders.destroy', $typed))->assertSessionHasNoErrors();

        // S-4: a part that is no longer an active sales item is refused, naming the part.
        $rose->update(['status' => 'Inactive']);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))
            ->assertSessionHasErrors(['sales_order' => 'Part ROSE-1 is not an active sales item of Test Party, so the sales order cannot be made. Change the PO\'s items first.']);
        $rose->update(['status' => 'Active']);
        // Another customer's item.
        $other = $this->customer('Orion Agro');
        $rose->update(['party_id' => $other->id]);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasErrors('sales_order');
        $rose->update(['party_id' => $party->id]);
        // An inactive customer.
        $party->update(['status' => 'Inactive']);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))
            ->assertSessionHasErrors(['sales_order' => 'Test Party is not an active Customer (or Both) in the Party master, so a sales order cannot be made for it.']);
        $party->update(['status' => 'Active']);
        $this->assertSame(0, SalesOrder::count());

        // One active order per PO.
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $so = SalesOrder::firstOrFail();
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))
            ->assertSessionHasErrors(['sales_order' => 'Sales order '.$so->so_no.' is already made from this PO.']);
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame(1, ActivityLog::where('action', 'customer_po.sales_order_made')->count());
        // Deleted: a new one can be made.
        $this->actingAs($sales)->delete(route('sales.orders.destroy', $so))->assertSessionHasNoErrors();
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('Create Sales Order')->assertDontSee('Open sales order');
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame($po->id, SalesOrder::firstOrFail()->customer_po_id);
        $this->assertNotSame($so->id, SalesOrder::firstOrFail()->id);
    }

    public function test_another_units_po_is_forbidden_and_old_po_menu_route_is_gone(): void
    {
        $party = $this->customer();
        $rose = $this->item($party, 'ROSE-1', 40);
        $sales = $this->sales();
        $crmPo = $this->crmPo($party, [$this->line($rose, 4, 41)], ['po_no' => 'KP/PO/1']);

        // The PO menu's route is gone (no PO menu since 02/11/2026); the CRM one works.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('po.sales-order'));
        $this->actingAs($sales)->post('/po/'.$crmPo->id.'/sales-order')->assertNotFound();
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $crmPo))->assertOk()->assertSee(route('crm.customer-pos.sales-order', $crmPo), false);

        // Another unit's PO: 403.
        $unit9 = Unit::create(['code' => 'U9', 'name' => 'Unit 9']);
        $theirs = $crmPo->replicate(['source']);
        $theirs->forceFill(['unit_id' => $unit9->id, 'po_no' => 'U9/1', 'source' => 'crm'])->save();
        $theirs->lines()->create(['line_no' => 1, 'item_id' => $rose->id, 'description' => 'Rose', 'quantity' => 1, 'rate' => 1, 'amount' => 1]);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $theirs))->assertForbidden();
        $this->assertSame(0, SalesOrder::count());

        // The order is made, and "From PO" opens the CRM page.
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $crmPo))->assertSessionHasNoErrors();
        $so = SalesOrder::firstOrFail();
        $this->assertSame([$party->id, 'KP/PO/1', $crmPo->id], [$so->party_id, $so->po_number, $so->customer_po_id]);
        $this->actingAs($sales)->get(route('sales.orders.show', $so))->assertOk()->assertSee(route('crm.customer-pos.show', $crmPo), false);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $crmPo))->assertOk()->assertSee('Open sales order');
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $crmPo))->assertSessionHasErrors('sales_order');
        // Sales right without PO view: the PO link on the order is plain text.
        Role::findOrCreate('SO only', 'web')->syncPermissions(['dashboard.view', 'sales-order.view', 'sales-order.add']);
        $soOnly = User::create(['name' => 'Sam', 'email' => 'sam@company.example', 'mobile' => '9822000041', 'password' => 'Secret2026', 'status' => 'Active']);
        $soOnly->assignRole('SO only');
        $soOnly->units()->sync([Unit::first()->id]);
        $this->actingAs($soOnly)->get(route('sales.orders.show', $so))->assertOk()->assertSee('From PO')->assertSee('KP/PO/1')
            ->assertDontSee(route('crm.customer-pos.show', $crmPo), false);
        $this->actingAs($soOnly)->post(route('crm.customer-pos.sales-order', $crmPo))->assertForbidden();
    }

    public function test_approved_order_from_a_po_shows_in_the_tax_invoice_po_no_list(): void
    {
        $party = $this->customer();
        $rose = $this->item($party, 'ROSE-1', 40);
        $fern = $this->item($party, 'FERN-2', 12);
        $sales = $this->sales();
        $po = $this->crmPo($party, [$this->line($rose, 10, 45), $this->line($fern, 20, 12)]);

        // Before: no approved order, so the hint shows (empty list from party info).
        $this->assertSame([], $this->actingAs($sales)->getJson(route('sales.parties.info', $party))->json('orders'));
        // With no party chosen yet, the hint is there but hidden; the page script shows it for a party with no orders.
        $page = $this->actingAs($sales)->get(route('sales.invoices.index'))->assertOk()->assertSee('No approved sales order for this party.');
        $this->assertMatchesRegularExpression('/id="invPoNone"\s+hidden/', $page->getContent());

        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $so = SalesOrder::firstOrFail();
        // A Draft is not offered yet.
        $this->assertSame([], $this->actingAs($sales)->getJson(route('sales.parties.info', $party))->json('orders'));
        $this->actingAs($sales)->post(route('sales.orders.submit', $so))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('sales.orders.approve', $so))->assertSessionHasNoErrors();
        $this->assertSame('Approved', $so->fresh()->status);

        $this->assertSame([['id' => $so->id, 'label' => 'PO 21312312 · '.$so->so_no]], $this->actingAs($sales)->getJson(route('sales.parties.info', $party))->json('orders'));
        $page = $this->actingAs($sales)->get(route('sales.invoices.create', ['order' => $so->id]))->assertOk()->assertSee('PO 21312312 · '.$so->so_no);
        $this->assertMatchesRegularExpression('/id="invPoNone"\s+hidden/', $page->getContent());
        $lines = $this->actingAs($sales)->getJson(route('sales.orders.po-lines', $so))->assertOk()->json();
        $this->assertSame([$rose->id, $fern->id], array_column($lines, 'item_id'));
        $this->assertEquals([10, 20], array_column($lines, 'balance'));
        $this->assertEquals([45, 12], array_column($lines, 'rate'));
    }
}
