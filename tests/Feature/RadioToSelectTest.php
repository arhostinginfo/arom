<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segmented radio groups shown as drop-downs (02/11/2026): each converted page renders a <select> with the same name,
 * options and selected value as the radios had, a <label for> tied to it, and no radio group; saving still stores the
 * chosen value.
 */
class RadioToSelectTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    /** The select with this name (and id when given): its options [value => label] and the selected value. */
    private function select(string $html, string $name, ?string $id = null): array
    {
        $re = '/<select\b[^>]*\bname="'.preg_quote($name, '/').'"[^>]*>(.*?)<\/select>/s';
        preg_match_all($re, $html, $all, PREG_SET_ORDER);
        $this->assertNotEmpty($all, "No <select name=\"$name\">");
        $m = $id === null ? $all[0] : collect($all)->first(fn ($x) => str_contains($x[0], 'id="'.$id.'"'));
        $this->assertNotNull($m, "No <select name=\"$name\" id=\"$id\">");
        preg_match('/\bid="([^"]+)"/', $m[0], $idm);
        $this->assertStringContainsString('<label for="'.$idm[1].'"', $html, 'The select has a label tied to it');
        preg_match_all('/<option value="([^"]*)"([^>]*)>([^<]*)<\/option>/', $m[1], $opts, PREG_SET_ORDER);
        $options = [];
        $selected = [];
        foreach ($opts as $o) {
            $options[$o[1]] = html_entity_decode($o[3]);
            if (str_contains($o[2], 'selected')) {
                $selected[] = $o[1];
            }
        }
        $this->assertLessThanOrEqual(1, count($selected));

        return ['options' => $options, 'selected' => $selected[0] ?? null, 'tag' => $m[0]];
    }

    private function noRadios(string $html): void
    {
        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertStringNotContainsString('role="radiogroup"', $html);
    }

    public function test_approval_rules_show_yes_no_drop_downs_and_save_the_choice(): void
    {
        $admin = $this->admin();
        ApprovalRule::for('quotation')->update(['is_required' => true]);
        ApprovalRule::for('sales_order')->update(['is_required' => false]);

        $html = $this->actingAs($admin)->get('/admin/approval')->assertOk()->getContent();
        $this->noRadios($html);
        $q = $this->select($html, 'is_required', 'is_required');
        $this->assertSame(['1' => 'Yes', '0' => 'No'], $q['options']);
        $this->assertSame('1', $q['selected']);
        $this->assertStringContainsString('aria-required="true"', $q['tag']);
        $this->assertSame('0', $this->select($html, 'is_required', 'so_is_required')['selected']);
        $this->select($html, 'is_required', 'st_is_required');

        $this->actingAs($admin)->put('/admin/approval', ['is_required' => '0', 'approve_above_amount' => ''])->assertRedirect('/admin/approval');
        $this->assertFalse((bool) ApprovalRule::for('quotation')->is_required);
        $this->actingAs($admin)->put('/admin/approval/sales-order', ['_so' => '1', 'is_required' => '1', 'approve_above_amount' => ''])->assertSessionHasNoErrors();
        $this->assertTrue((bool) ApprovalRule::for('sales_order')->is_required);
        $this->actingAs($admin)->put('/admin/approval/stock-transfer', ['_st' => '1', 'is_required' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) ApprovalRule::for('stock_transfer')->is_required);

        $html = $this->actingAs($admin)->get('/admin/approval')->getContent();
        $this->assertSame('0', $this->select($html, 'is_required', 'is_required')['selected']);
        $this->assertSame('1', $this->select($html, 'is_required', 'so_is_required')['selected']);
        $this->assertSame('0', $this->select($html, 'is_required', 'st_is_required')['selected']);
    }

    public function test_party_form_type_dr_cr_and_status_are_drop_downs_and_save(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/admin/masters/parties/create')->assertOk()->getContent();
        $this->noRadios($html);
        $t = $this->select($html, 'type');
        $this->assertSame(['Customer' => 'Customer', 'Supplier' => 'Supplier', 'Both' => 'Both'], $t['options']);
        $this->assertSame('Customer', $t['selected']);
        $s = $this->select($html, 'status');
        $this->assertSame(['Active' => 'Active', 'Inactive' => 'Inactive'], $s['options']);
        $this->assertSame('Active', $s['selected']);
        if (str_contains($html, 'name="opening_balance_type"')) {
            $d = $this->select($html, 'opening_balance_type');
            $this->assertSame(['DR' => 'DR', 'CR' => 'CR'], $d['options']);
            $this->assertSame('DR', $d['selected']);
        }

        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'Apex', 'type' => 'Supplier', 'status' => 'Inactive', 'billing_address' => 'Chakan',
            'gstin' => '27AABCZ9999F1Z5', 'opening_balance' => '100', 'opening_balance_type' => 'CR', 'currency_id' => Currency::defaultId()])->assertSessionHasNoErrors();
        $p = Party::where('name', 'Apex')->firstOrFail();
        $this->assertSame(['Supplier', 'Inactive', 'CR'], [$p->type, $p->status, $p->opening_balance_type]);

        $html = $this->actingAs($admin)->get("/admin/masters/parties/{$p->id}")->assertOk()->getContent();
        $this->assertSame('Supplier', $this->select($html, 'type')['selected']);
        $this->assertSame('Inactive', $this->select($html, 'status')['selected']);
        if (str_contains($html, 'name="opening_balance_type"')) {
            $this->assertSame('CR', $this->select($html, 'opening_balance_type')['selected']);
        }

        // A refused save shows the choice that was sent (old input).
        $this->actingAs($admin)->from('/admin/masters/parties/create')->post('/admin/masters/parties', ['name' => '', 'type' => 'Both', 'status' => 'Inactive'])->assertSessionHasErrors('name');
        $html = $this->actingAs($admin)->get('/admin/masters/parties/create')->getContent();
        $this->assertSame('Both', $this->select($html, 'type')['selected']);
        $this->assertSame('Inactive', $this->select($html, 'status')['selected']);
    }

    public function test_item_form_type_and_status_are_drop_downs_and_save(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/admin/masters/items/create')->assertOk()->getContent();
        $this->noRadios($html);
        $t = $this->select($html, 'type');
        $this->assertSame(['Sales' => 'Sales', 'Purchase' => 'Purchase', 'Both' => 'Both'], $t['options']);
        $this->assertSame('Sales', $t['selected']);
        $this->assertSame('Active', $this->select($html, 'status')['selected']);

        $tax = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '7325', 'type' => 'HSN', 'description' => 'Cast articles', 'tax_structure_id' => $tax->id, 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/masters/items', ['description' => 'Bracket', 'item_no' => 'BRK-1', 'type' => 'Both', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => Uom::first()->id, 'tax_structure_id' => TaxStructure::first()->id, 'status' => 'Inactive', 'currency_id' => Currency::defaultId()])->assertSessionHasNoErrors();
        $item = Item::where('item_no', 'BRK-1')->firstOrFail();
        $this->assertSame(['Both', 'Inactive'], [$item->type, $item->status]);
        $html = $this->actingAs($admin)->get("/admin/masters/items/{$item->id}")->assertOk()->getContent();
        $this->assertSame('Both', $this->select($html, 'type')['selected']);
        $this->assertSame('Inactive', $this->select($html, 'status')['selected']);
    }

    public function test_user_and_employee_status_are_drop_downs_and_save(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/admin/users/create')->assertOk()->getContent();
        $this->noRadios($html);
        $s = $this->select($html, 'status');
        $this->assertSame(['Active' => 'Active', 'Inactive' => 'Inactive'], $s['options']);
        $this->assertSame('Active', $s['selected']);

        $this->actingAs($admin)->post('/admin/users', ['name' => 'Rahul Kulkarni', 'email' => 'rahul@company.example', 'mobile' => '+91 98220 00002',
            'password' => 'Purchase2026', 'password_confirmation' => 'Purchase2026', 'role' => 'Purchase', 'units' => [Unit::first()->id],
            'status' => 'Inactive', 'permissions' => []])->assertSessionHasNoErrors();
        $u = User::where('email', 'rahul@company.example')->firstOrFail();
        $this->assertSame('Inactive', $u->status->value);
        $html = $this->actingAs($admin)->get("/admin/users/{$u->id}/edit")->assertOk()->getContent();
        $this->assertSame('Inactive', $this->select($html, 'status')['selected']);

        $html = $this->actingAs($admin)->get('/admin/masters/employees/create')->assertOk()->getContent();
        $this->noRadios($html);
        $this->assertSame('Active', $this->select($html, 'status')['selected']);
    }

    public function test_purchase_pages_show_invoice_type_result_and_source_type_as_drop_downs(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/purchase/grns')->assertOk()->getContent();
        $this->noRadios($html);
        $g = $this->select($html, 'invoice_type');
        $this->assertSame(['Purchase' => 'Purchase', 'Credit' => 'Credit', 'Debit' => 'Debit'], $g['options']);
        $this->assertSame('Purchase', $g['selected']);
        $this->assertStringContainsString('aria-required="true"', $g['tag']);

        $html = $this->actingAs($admin)->get('/purchase/stock-transfers')->assertOk()->getContent();
        $this->noRadios($html);
        if (str_contains($html, 'name="source_type"')) {
            $st = $this->select($html, 'source_type');
            // A new transfer starts on Purchase, so there is no blank choice.
            $this->assertSame(['Purchase' => 'Purchase', 'Credit' => 'Credit', 'Debit' => 'Debit'], $st['options']);
            $this->assertSame('Purchase', $st['selected']);
        }
    }

    public function test_stock_transfer_source_type_saves_and_an_older_transfer_without_one_keeps_a_blank_choice(): void
    {
        $admin = $this->admin();
        [$u1, $u2] = Unit::orderBy('id')->get()->all();
        $tax = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '0602', 'type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $tax->id, 'is_active' => true]);
        $item = Item::create(['description' => 'Plant', 'item_no' => 'ARC-0001', 'type' => 'Both', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => Uom::where('code', 'NOS')->first()->id, 'tax_structure_id' => $tax->id, 'price' => 100, 'status' => 'Active']);
        \Illuminate\Support\Facades\DB::transaction(fn () => \App\Services\StockLedger::move($u1->id, $item->id, today()->format('Y-m-d'), 100, 0, 'test_opening', 1));

        $this->actingAs($admin)->post('/purchase/stock-transfers', ['transfer_date' => today()->format('d/m/Y'), 'from_unit_id' => $u1->id, 'to_unit_id' => $u2->id,
            'source_type' => 'Debit', 'remarks' => '', 'lines' => [['item_id' => $item->id, 'quantity' => '5', 'remarks' => '']], '_form' => 'transfer'])->assertSessionHasNoErrors();
        $t = \App\Models\StockTransfer::latest('id')->firstOrFail();
        $this->assertSame('Debit', $t->source_type);
        $st = $this->select($this->actingAs($admin)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->getContent(), 'source_type');
        $this->assertSame(['Purchase', 'Credit', 'Debit'], array_keys($st['options']));
        $this->assertSame('Debit', $st['selected']);

        $t->forceFill(['source_type' => null])->saveQuietly();
        $st = $this->select($this->actingAs($admin)->get("/purchase/stock-transfers/{$t->id}")->assertOk()->getContent(), 'source_type');
        $this->assertSame(['', 'Purchase', 'Credit', 'Debit'], array_keys($st['options']));
        $this->assertNull($st['selected']);
    }

    public function test_report_panels_show_detail_and_format_drop_downs_and_download_with_the_choice(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/reports/sales/sales')->assertOk()->getContent();
        $this->noRadios($html);
        $d = $this->select($html, 'detail');
        $this->assertSame(['summary' => 'Summary', 'detailed' => 'Detailed'], $d['options']);
        $this->assertSame('summary', $d['selected']);
        $f = $this->select($html, 'format');
        $this->assertSame(['csv' => 'Excel (CSV)', 'pdf' => 'PDF'], $f['options']);
        $this->assertSame('csv', $f['selected']);

        $year = \App\Models\FinancialYear::forDate();
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];
        $res = $this->actingAs($admin)->get('/reports/sales/sales/download?'.http_build_query($range + ['detail' => 'detailed', 'format' => 'csv']))->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));

        // A refused download shows the chosen options again (old input).
        $this->actingAs($admin)->from('/reports/sales/sales')->get('/reports/sales/sales/download?'.http_build_query(['from' => 'x', 'to' => 'y', 'detail' => 'detailed', 'format' => 'pdf']))
            ->assertSessionHasErrors('from');
        $html = $this->actingAs($admin)->get('/reports/sales/sales')->getContent();
        $this->assertSame('detailed', $this->select($html, 'detail')['selected']);
        $this->assertSame('pdf', $this->select($html, 'format')['selected']);

        $html = $this->actingAs($admin)->get('/reports/purchase/grn')->assertOk()->getContent();
        $this->noRadios($html);
        $this->assertSame('csv', $this->select($html, 'format')['selected']);
        $html = $this->actingAs($admin)->get('/reports/purchase/grn?format=pdf')->assertOk()->getContent();
        $this->assertSame('pdf', $this->select($html, 'format')['selected']);
    }

    public function test_crm_report_format_is_a_drop_down(): void
    {
        $admin = $this->admin();
        $page = $this->actingAs($admin)->get('/reports/crm/enquiries');
        if ($page->status() !== 200) {
            $this->markTestSkipped('CRM reports are off in this setup.');
        }
        $html = $page->getContent();
        $this->noRadios($html);
        $this->assertSame('csv', $this->select($html, 'format')['selected']);
    }
}
