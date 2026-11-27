<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * (26/11/2026) Customer PO: Plant deployment details uploaded from the PO list (a document of the PO); the Target
 * delivery date, a Revised delivery date once it has passed (with a reason), Delivered on; the Dashboard's Delivery
 * overdue count in red and the Pending deliveries page it opens.
 */
class CustomerPoDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    protected function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function d(int $days): string
    {
        return today()->addDays($days)->format('d/m/Y');
    }

    /** A PO against a sent quotation of a new customer. */
    private function po(string $no, string $client, array $over = []): CustomerPo
    {
        $party = Party::create(['name' => $client, 'type' => 'Customer', 'billing_address' => 'Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $e = $this->crmEnquiry(['party_id' => $party->id, 'prospect_id' => '']);
        $this->actingAs($this->admin())->post(route('crm.quotations.store'), $this->newQuotationPayload(['enquiry_id' => $e->id]))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('crm.quotations.submit', $q));
        $this->actingAs($this->admin())->post(route('crm.quotations.sent', $q));
        $this->actingAs($this->admin())->post(route('crm.customer-pos.store'), $this->payload($q->id, $no, $over))->assertSessionHasNoErrors();

        return CustomerPo::where('po_no', $no)->firstOrFail();
    }

    private function payload(int $quotationId, string $no, array $over = []): array
    {
        return array_merge(['quotation_id' => $quotationId, 'po_no' => $no, 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026', 'lines_json' => '[]'], $over);
    }

    private function update(CustomerPo $po, array $over)
    {
        return $this->actingAs($this->admin())->put(route('crm.customer-pos.update', $po), $this->payload($po->quotation_id, $po->po_no, $over));
    }

    public function test_plant_deployment_details_upload_from_the_po_list(): void
    {
        Storage::fake(\App\Services\DocumentService::diskName());
        $po = $this->po('KP/PO/1', 'Lakeview Towers');
        $list = $this->actingAs($this->admin())->get(route('crm.customer-pos.list'))->assertOk()->getContent();
        $this->assertStringContainsString('<th>Plant deployment</th>', $list);
        $this->assertStringContainsString('data-deploy="'.route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]).'"', $list);
        $this->assertStringContainsString('Upload Plant Deployment Details', $list);

        $this->actingAs($this->admin())->from(route('crm.customer-pos.list'))->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), [
            'category' => 'Plant deployment details', 'file' => UploadedFile::fake()->create('deployment.xlsx', 30),
        ])->assertRedirect(route('crm.customer-pos.list'))->assertSessionHasNoErrors();
        $doc = Document::where('documentable_type', 'customer_po')->where('documentable_id', $po->id)->sole();
        $this->assertSame(['Plant deployment details', 'deployment.xlsx'], [$doc->category, $doc->original_name]);
        $this->actingAs($this->admin())->get(route('crm.customer-pos.list'))->assertSee('1 file');

        // a refused file: the message shows on the list
        $this->actingAs($this->admin())->from(route('crm.customer-pos.list'))->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), [
            'category' => 'Plant deployment details', 'file' => UploadedFile::fake()->create('virus.exe', 3),
        ])->assertRedirect(route('crm.customer-pos.list'));
        $this->actingAs($this->admin())->get(route('crm.customer-pos.list'))->assertOk()->assertSee('Plant deployment details not uploaded.');

        // view-only user: no Upload button
        Role::findOrCreate('PO viewer', 'web')->syncPermissions(['dashboard.view', 'customer-po.view']);
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@company.example', 'mobile' => '9822000041', 'password' => 'Secret2026', 'status' => 'Active']);
        $viewer->assignRole('PO viewer');
        $viewer->units()->sync([Unit::first()->id]);
        $this->actingAs($viewer)->get(route('crm.customer-pos.list'))->assertOk()->assertDontSee('data-deploy=', false);
    }

    public function test_revised_delivery_date_only_after_the_target_has_passed(): void
    {
        $po = $this->po('KP/PO/2', 'Orchid Mall', ['target_delivery_date' => $this->d(5)]);
        $this->assertSame(today()->addDays(5)->toDateString(), $po->target_delivery_date->toDateString());
        $this->assertFalse($po->deliveryOverdue());

        // target not passed yet: no revised date
        $this->update($po, ['target_delivery_date' => $this->d(5), 'revised_delivery_date' => $this->d(9), 'revised_reason' => 'Plants not ready'])
            ->assertSessionHasErrors(['revised_delivery_date' => 'A revised delivery date can be added once the target delivery date ('.$this->d(5).') has passed.']);

        // target passed: revised must be after it and needs a reason
        $this->update($po, ['target_delivery_date' => $this->d(-3)])->assertSessionHasNoErrors();
        $this->assertTrue($po->fresh()->deliveryOverdue());
        $this->update($po, ['target_delivery_date' => $this->d(-3), 'revised_delivery_date' => $this->d(-4), 'revised_reason' => 'x'])
            ->assertSessionHasErrors(['revised_delivery_date' => 'The revised delivery date must be after the target delivery date.']);
        $this->update($po, ['target_delivery_date' => $this->d(-3), 'revised_delivery_date' => $this->d(4)])
            ->assertSessionHasErrors(['revised_reason' => 'Enter the reason for the revised delivery date.']);
        $this->update($po, ['target_delivery_date' => $this->d(-3), 'revised_delivery_date' => $this->d(4), 'revised_reason' => 'Rain at site'])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame([today()->addDays(4)->toDateString(), 'Rain at site', false], [$po->revised_delivery_date->toDateString(), $po->revised_reason, $po->deliveryOverdue()]);
        $this->assertSame($this->d(4), \App\Models\ActivityLog::where('action', 'customer_po.updated')->latest('id')->first()->changes['Revised delivery date']['new'] ?? null);

        // delivered: not in the future; once delivered it is no longer pending
        $this->update($po, ['target_delivery_date' => $this->d(-3), 'revised_delivery_date' => $this->d(4), 'revised_reason' => 'Rain at site', 'delivered_on' => $this->d(1)])
            ->assertSessionHasErrors(['delivered_on' => 'The delivered date cannot be in the future.']);
        $this->update($po, ['target_delivery_date' => $this->d(-3), 'revised_delivery_date' => $this->d(4), 'revised_reason' => 'Rain at site', 'delivered_on' => $this->d(0)])->assertSessionHasNoErrors();
        $this->assertSame(0, CustomerPo::pendingDelivery()->count());

        // the form: revised field open once the target has passed
        $html = $this->actingAs($this->admin())->get(route('crm.customer-pos.show', $po))->assertOk()->getContent();
        $this->assertStringContainsString('<label for="target_delivery_date">Target delivery date', $html);
        $this->assertMatchesRegularExpression('#<div class="field" data-rev-field\s*>\s*<label for="revised_delivery_date">#', $html);
    }

    private function pageSetup(array $set): void
    {
        $fields = [];
        foreach (app(\App\Services\PageSetupService::class)->fields('customer-po') as $f => $cur) {
            [$vis, $req] = $set[$f] ?? [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put('/admin/page-setup/customer-po', ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
        app()->forgetInstance(\App\Services\PageSetupService::class);
    }

    public function test_kept_revised_date_clearing_it_and_page_setup(): void
    {
        $po = $this->po('KP/PO/20', 'Kept Ltd', ['target_delivery_date' => $this->d(-6)]);
        $this->update($po, ['target_delivery_date' => $this->d(-6), 'revised_delivery_date' => $this->d(-2), 'revised_reason' => 'Rain'])->assertSessionHasNoErrors();
        // saved again with the same revised date (now in the past too): allowed
        $this->update($po, ['target_delivery_date' => $this->d(-6), 'revised_delivery_date' => $this->d(-2), 'revised_reason' => 'Rain', 'remarks' => 'x'])->assertSessionHasNoErrors();
        // revised cleared: the reason goes with it
        $this->update($po, ['target_delivery_date' => $this->d(-6), 'revised_delivery_date' => '', 'revised_reason' => 'Rain'])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame([null, null], [$po->revised_delivery_date, $po->revised_reason]);

        // revision mandatory in Page setup: needed once the target has passed, unless delivered by then
        $this->pageSetup(['delivery_revision' => [true, true]]);
        $this->assertStringContainsString('Revised delivery date<span', $this->actingAs($this->admin())->get(route('crm.customer-pos.show', $po))->getContent());
        $this->update($po, ['target_delivery_date' => $this->d(-6)])
            ->assertSessionHasErrors(['revised_delivery_date' => 'The target delivery date ('.$this->d(-6).') has passed: enter the revised delivery date.']);
        $this->update($po, ['target_delivery_date' => $this->d(-6), 'delivered_on' => $this->d(-7)])->assertSessionHasNoErrors();
        $this->update($po, ['target_delivery_date' => $this->d(-6), 'delivered_on' => $this->d(-1)])->assertSessionHasErrors('revised_delivery_date');
        $this->po('KP/PO/21', 'Future Ltd', ['target_delivery_date' => $this->d(3)]); // target not passed: not needed

        // target and revision hidden: what is saved stays, the form does not show them
        $po->update(['revised_delivery_date' => today()->subDay(), 'revised_reason' => 'Truck', 'delivered_on' => null]);
        $this->pageSetup(['delivery_revision' => [false, false], 'target_delivery_date' => [false, false]]);
        $this->update($po, ['target_delivery_date' => $this->d(9), 'revised_delivery_date' => '', 'revised_reason' => ''])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame([today()->subDays(6)->toDateString(), 'Truck'], [$po->target_delivery_date->toDateString(), $po->revised_reason]);
        $html = $this->actingAs($this->admin())->get(route('crm.customer-pos.show', $po))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="target_delivery_date"', $html);
        $this->assertStringNotContainsString('id="revised_delivery_date"', $html);

        // target mandatory
        $this->pageSetup(['target_delivery_date' => [true, true]]);
        $this->update($po, ['target_delivery_date' => ''])->assertSessionHasErrors('target_delivery_date');
    }

    public function test_po_list_shows_upload_and_date_errors_apart(): void
    {
        $po = $this->po('KP/PO/30', 'Both Errors <b>Co</b>');
        $po->update(['po_no' => 'KP/"><script>x</script>']);
        $list = route('crm.customer-pos.list', ['from' => '99/99/2026']);
        $this->actingAs($this->admin())->from($list)->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), [
            'category' => 'Plant deployment details', 'file' => UploadedFile::fake()->create('virus.exe', 3),
        ])->assertRedirect($list);
        $html = $this->actingAs($this->admin())->get($list)->assertOk()->getContent();
        $this->assertStringContainsString('<b>Plant deployment details not uploaded.</b>', $html);
        $this->assertStringContainsString('<b>Check the dates.</b><ul><li>Enter From date as a real date, dd/mm/yyyy.</li></ul>', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('data-po="KP/&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
        // the PO page (index / search) and the CSV still work
        $this->actingAs($this->admin())->get(route('crm.customer-pos.index'))->assertOk();
        $csv = $this->actingAs($this->admin())->get(route('crm.customer-pos.export'))->assertOk()->streamedContent();
        $this->assertSame(['PO / Agreement no', 'PO date', 'Customer', 'Enquiry no', 'Quotation', 'Site', 'Items', 'Items total', 'PO value', 'Valid from', 'Valid to', 'Received by', 'Received date'],
            str_getcsv(ltrim(strtok($csv, "\n"), "\u{FEFF}")));
        $this->assertStringNotContainsString('delivery', strtolower($csv));
    }

    public function test_dashboard_overdue_count_and_pending_deliveries(): void
    {
        $late = $this->po('KP/PO/3', 'Alpha Corp', ['target_delivery_date' => $this->d(-10)]);
        $revisedLate = $this->po('KP/PO/4', 'Beta Ltd', ['target_delivery_date' => $this->d(5)]);
        $revisedLate->update(['target_delivery_date' => today()->subDays(20), 'revised_delivery_date' => today()->subDays(2), 'revised_reason' => 'Truck']);
        $onTime = $this->po('KP/PO/5', 'Gamma Inc', ['target_delivery_date' => $this->d(7)]);
        $this->po('KP/PO/6', 'Delta Co', ['target_delivery_date' => $this->d(-1), 'delivered_on' => $this->d(-1)]);
        $this->po('KP/PO/7', 'Epsilon', []); // no target: not a pending delivery
        // another unit's overdue PO: not counted nor listed for the unit on the top bar
        $unit9 = Unit::create(['code' => 'U9', 'name' => 'Unit 9']);
        $this->po('KP/PO/8', 'Foreign Co', ['target_delivery_date' => $this->d(-30)])->forceFill(['unit_id' => $unit9->id])->save();
        Cache::flush();

        $dash = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="card kpi kpidue" href="'.preg_quote(route('crm.customer-pos.deliveries'), '#').'"><span class="ovl">Delivery overdue</span><span class="v"><svg[^>]*><use href="\#i-alert"/></svg>2</span>#', $dash);

        $page = $this->actingAs($this->admin())->get(route('crm.customer-pos.deliveries'))->assertOk()->getContent();
        foreach (['Client name', 'PO no', 'Target delivery date', 'Revised delivery date'] as $h) {
            $this->assertStringContainsString('<th>'.$h.'</th>', $page);
        }
        // earliest due first: Alpha (-10), Beta (revised -2), Gamma (+7); delivered and no-target POs left out
        $this->assertLessThan(strpos($page, 'KP/PO/4'), strpos($page, 'KP/PO/3'));
        $this->assertLessThan(strpos($page, 'KP/PO/5'), strpos($page, 'KP/PO/4'));
        $this->assertStringNotContainsString('KP/PO/6', $page);
        $this->assertStringNotContainsString('KP/PO/7', $page);
        $this->assertStringNotContainsString('KP/PO/8', $page);
        // the admin switched to Unit 9 on the top bar: only its PO
        $this->admin()->units()->syncWithoutDetaching([$unit9->id]);
        $nine = $this->actingAs($this->admin()->fresh())->withSession(['unit_id' => $unit9->id])->get(route('crm.customer-pos.deliveries'))->getContent();
        $this->assertStringContainsString('KP/PO/8', $nine);
        $this->assertStringNotContainsString('KP/PO/3', $nine);
        $this->assertMatchesRegularExpression('#Delivery overdue</span><span class="v"><svg[^>]*><use href="\#i-alert"/></svg>1</span>#',
            $this->actingAs($this->admin()->fresh())->withSession(['unit_id' => $unit9->id])->get('/dashboard')->getContent());
        $this->withSession(['unit_id' => Unit::first()->id]);
        $this->assertStringContainsString('Overdue 10 day(s)', $page);
        $this->assertStringContainsString('Truck', $page);
        $this->assertStringContainsString('2 overdue', $page);

        $only = $this->actingAs($this->admin())->get(route('crm.customer-pos.deliveries', ['show' => 'overdue']))->getContent();
        $this->assertStringNotContainsString('KP/PO/5', $only);
        $this->assertStringContainsString('KP/PO/3', $only);

        // no right to POs: no tile and the page is refused
        Role::findOrCreate('Dash only', 'web')->syncPermissions(['dashboard.view', 'enquiry.view']);
        $u = User::create(['name' => 'Dash', 'email' => 'dash@company.example', 'mobile' => '9822000042', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Dash only');
        $u->units()->sync([Unit::first()->id]);
        $this->actingAs($u)->get('/dashboard')->assertDontSee('Delivery overdue');
        $this->actingAs($u)->get(route('crm.customer-pos.deliveries'))->assertForbidden();
    }
}
