<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Enums\QuotationStatus;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\CustomerPoLine;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\ModuleSetting;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\ModuleSettings;
use App\Services\PageSetupService;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Separate PO menu removed (02/11/2026): CRM › 6 PO / Agreement is the only PO page again. Consolidates what still
 * applies from the PO menu tests (PoModuleTest, PoModuleReviewTest, PoDualEntryTest, PoDualEntryReviewTest,
 * SeparatePoFinalReviewTest, CustomerPoDirectTest, CustomerPoDirectReviewTest, now deleted): no PO menu, routes or switch;
 * the CRM rules from before 29/10/2026; items from the Item master and their checks; units, forged fields and lookups;
 * rights; documents; the CRM switch; used items / UOMs. Create Sales Order is in PoToSalesOrderTest.
 */
class CrmCustomerPoTest extends TestCase
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

    private function user(string $role, array $permissions, string $email, string $mobile): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        $u = User::create(['name' => ucfirst($role), 'email' => $email, 'mobile' => $mobile, 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function poClerk(): User
    {
        return $this->user('PO clerk', ['dashboard.view', 'customer-po.view', 'customer-po.add', 'customer-po.edit', 'customer-po.delete'], 'clerk@company.example', '9822000031');
    }

    /** @return array<string, list<string>> */
    private function bar(User $u): array
    {
        $this->actingAs($u);
        app()->forgetInstance(ModuleSettings::class);

        return collect(Menu::bar(Menu::build($u, Request::create('/dashboard'), null))['items'])
            ->mapWithKeys(fn ($i) => [$i['key'] => array_column($i['subs'], 'title')])->all();
    }

    private function setModules(array $modules): void
    {
        $all = array_merge(['crm' => true, 'site_visit' => true, 'boq' => true, 'costing' => true], $modules);
        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => array_map(fn ($v) => $v ? '1' : '0', $all)])->assertSessionHasNoErrors();
        app()->forgetInstance(ModuleSettings::class);
        Cache::flush();
    }

    private function setCrmLines(bool $visible, bool $required): void
    {
        $fields = [];
        foreach (app(PageSetupService::class)->fields('customer-po') as $f => $cur) {
            [$vis, $req] = $f === 'crm_lines' ? [$visible, $required] : [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put('/admin/page-setup/customer-po', ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
        app()->forgetInstance(PageSetupService::class);
    }

    private function customer(string $name = 'Lakeview Towers', string $type = 'Customer'): Party
    {
        return Party::create(['name' => $name, 'type' => $type, 'billing_address' => 'Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
    }

    private function item(?Party $party, string $no, float $price, string $type = 'Sales', string $status = 'Active'): Item
    {
        $gst = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::firstOrCreate(['code' => '0602'], ['type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $gst->id, 'is_active' => true]);

        return Item::create(['party_id' => $party?->id, 'description' => 'Plant '.$no, 'item_no' => $no, 'type' => $type, 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => Uom::where('code', 'NOS')->value('id'), 'tax_structure_id' => $gst->id, 'price' => $price, 'currency_id' => Currency::defaultId(), 'status' => $status]);
    }

    /** What CRM › 6 PO / Agreement posts (no Customer, no Enquiry). */
    private function crmPayload(mixed $q, array $over = [], array $lines = []): array
    {
        return array_merge(['quotation_id' => $q instanceof Quotation ? $q->id : $q, 'po_no' => 'KP/PO/1', 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026', 'lines_json' => json_encode($lines)], $over);
    }

    private function line(Item $i, $qty, $rate = '', array $over = []): array
    {
        return array_merge(['item_id' => (string) $i->id, 'description' => '', 'quantity' => (string) $qty, 'uom_id' => '', 'rate' => (string) $rate], $over);
    }

    /** A quotation from a new enquiry of $party (a prospect's enquiry when $party is null). */
    private function quotation(?Party $party, bool $send = true): Quotation
    {
        $e = $this->crmEnquiry($party ? ['party_id' => $party->id, 'prospect_id' => ''] : []);
        $this->actingAs($this->admin())->post(route('crm.quotations.store'), $this->newQuotationPayload(['enquiry_id' => $e->id]))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        if ($send) {
            $this->actingAs($this->admin())->post(route('crm.quotations.submit', $q));
            $this->actingAs($this->admin())->post(route('crm.quotations.sent', $q));
        }

        return $q->refresh();
    }

    public function test_po_menu_is_gone_and_crm_keeps_6_po_agreement(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();
        $q = $this->quotation($this->customer());

        // No po.* routes, no 'po' switch, no PO menu, no PO register.
        $this->assertSame([], collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_starts_with($n, 'po.'))->values()->all());
        $this->assertArrayNotHasKey('po', config('nursery.modules'));
        $this->assertArrayNotHasKey('PO', config('nursery.reports'));
        $this->assertArrayNotHasKey('PO', config('nursery.pages'));
        $this->assertArrayHasKey('customer-po', config('nursery.pages.CRM'));
        $this->assertSame(['PO / Agreement received', 'crm'], [config('nursery.pages.CRM.customer-po.label'), config('nursery.pages.CRM.customer-po.module')]);
        $this->assertSame('CRM', config('nursery.page_fields.customer-po.group'));
        foreach ([$admin, $sales] as $u) {
            $bar = $this->bar($u);
            $this->assertArrayNotHasKey('po', $bar);
            $this->assertContains('6 PO / Agreement', $bar['crm']);
            // /po and its pages are not reachable.
            foreach (['/po', '/po/list', '/po/export', '/po/lookup', '/po/parties', '/po/items', '/po/reports/register', '/po/reports/register/download'] as $url) {
                $this->actingAs($u)->get($url)->assertNotFound();
            }
            $this->actingAs($u)->post('/po', [])->assertNotFound();
            $this->actingAs($u)->get('/crm/pos')->assertOk()->assertDontSee('Open in PO menu')->assertDontSee('Direct PO')->assertSee('Quotation');
            $dash = $this->actingAs($u)->get('/dashboard')->assertOk();
            $dash->assertViewMissing('poTiles')->assertDontSee('dash-po-h');
            $this->assertSame(route('crm.customer-pos.index'), collect($dash->viewData('crmTiles'))->firstWhere('key', 'pos')['url']);
        }
        // Old bookmarks of a PO page or New PO open the CRM page (query kept).
        $this->actingAs($sales)->get('/po/create?quotation='.$q->id)->assertStatus(301)->assertRedirect(route('crm.customer-pos.create', ['quotation' => $q->id]));
        $this->actingAs($sales)->post(route('crm.customer-pos.store'), $this->crmPayload($q))->assertSessionHasNoErrors();
        $po = CustomerPo::firstOrFail();
        $this->assertSame('crm', $po->source);
        $this->actingAs($sales)->get('/po/'.$po->id)->assertStatus(301)->assertRedirect(route('crm.customer-pos.show', $po));
        $this->actingAs($sales)->post('/po/'.$po->id.'/sales-order')->assertNotFound();
        // A guest still goes to the login first.
        auth()->logout();
        $this->get('/crm/pos')->assertRedirect('/login');

        // Page setup and Access rights: no Separate PO menu switch or PO menu fields; the PO rights are CRM's again.
        $page = $this->actingAs($admin)->get(route('admin.page-setup.index'))->assertOk()->assertDontSee('Separate PO menu')
            ->assertDontSee('Link to CRM (enquiry / quotation)');
        $this->assertStringNotContainsString('name="modules[po]"', $page->getContent());
        $this->actingAs($admin)->get(route('admin.roles.create'))->assertOk()->assertSee('PO / Agreement received')->assertDontSee('the PO menu');
        // An old form that still posts po is refused, and nothing is saved.
        $this->actingAs($admin)->put('/admin/page-setup-modules', ['modules' => ['crm' => '1', 'po' => '1', 'site_visit' => '1', 'boq' => '1', 'costing' => '1']])->assertSessionHasErrors();
        $this->assertSame(0, ModuleSetting::where('module', 'po')->count());
    }

    public function test_crm_switched_off_takes_the_po_page_rights_and_documents_with_it(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $sales = $this->sales();
        $q = $this->quotation($this->customer());
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($q))->assertSessionHasNoErrors();
        $po = CustomerPo::firstOrFail();
        $this->actingAs($admin)->from('/dashboard')->post(route('crm.documents.store', ['customer_po', $po->id]), [
            'category' => 'Agreement', 'file' => UploadedFile::fake()->create('agreement.pdf', 10, 'application/pdf')])->assertRedirect('/dashboard');
        $doc = Document::firstOrFail();
        $this->actingAs($sales)->get(route('crm.documents.download', $doc))->assertOk();

        $this->setModules(['crm' => false]);
        // As before 01/11/2026: the PO rights go with CRM (gate refused, Admin too), and every PO page says switched off.
        $this->assertFalse($sales->fresh()->can('customer-po.view'));
        $this->assertFalse($admin->fresh()->can('customer-po.view'));
        $this->assertArrayNotHasKey('crm', $this->bar($sales));
        $this->assertArrayNotHasKey('po', $this->bar($sales));
        $this->actingAs($admin)->get('/crm/pos')->assertNotFound()->assertSee('CRM is switched off');
        $this->actingAs($admin)->get(route('crm.customer-pos.show', $po))->assertNotFound()->assertSee('CRM is switched off');
        $this->actingAs($admin)->get(route('crm.documents.download', $doc))->assertNotFound()->assertSee('CRM is switched off');
        $this->actingAs($admin)->get(route('admin.page-setup.index'))->assertOk()->assertSee('Switched off with CRM in Optional parts above');
        $dash = $this->actingAs($sales)->get('/dashboard')->assertOk();
        $this->assertNull(collect($dash->viewData('crmTiles'))->firstWhere('key', 'pos'));

        // On again: the PO and its document are back untouched.
        $this->setModules(['crm' => true]);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('KP/PO/1')->assertSee('agreement.pdf');
    }

    public function test_crm_page_rules_as_before_29_10(): void
    {
        $party = $this->customer();
        $admin = $this->admin();
        $draft = $this->quotation($party, false);
        $q1 = $this->quotation($party);
        $q2 = $this->quotation($party);
        $store = fn (array $p) => $this->actingAs($admin)->post(route('crm.customer-pos.store'), $p);

        // The page: Quotation* only, filled from ?quotation= only for a quotation that can take a PO.
        $this->actingAs($admin)->get(route('crm.customer-pos.create', ['quotation' => $q1->id]))->assertOk()
            ->assertSee('name="quotation_id"', false)->assertDontSee('name="party_id"', false)->assertDontSee('name="enquiry_id"', false)
            ->assertViewHas('po', fn ($po) => (int) $po->quotation_id === (int) $q1->id && (int) $po->party_id === (int) $party->id);
        $this->actingAs($admin)->get(route('crm.customer-pos.create', ['quotation' => $draft->id]))->assertOk()->assertViewHas('po', fn ($po) => $po->quotation_id === null);
        $this->assertFalse($this->actingAs($admin)->get(route('crm.customer-pos.create'))->viewData('quotationOptions')->has($draft->id));

        // Messages from before 29/10/2026.
        $store($this->crmPayload(''))->assertSessionHasErrors(['quotation_id' => 'Choose the quotation this PO is for.']);
        $store($this->crmPayload(999999))->assertSessionHasErrors(['quotation_id' => 'Choose a quotation made from an enquiry of your units.']);
        $store($this->crmPayload($draft))->assertSessionHasErrors(['quotation_id' => $draft->activityLabel().' is Draft. A PO can be entered only against the current revision of a quotation sent to the customer.']);
        $store($this->crmPayload($q1, ['po_no' => '', 'po_date' => '', 'received_date' => '', 'po_value' => '']))
            ->assertSessionHasErrors(['po_no', 'po_date', 'received_date', 'po_value' => 'Add at least one item, or enter the PO value.']);
        // A posted customer or direct type no longer makes a PO without a quotation.
        $store($this->crmPayload('', ['party_id' => $party->id, 'po_type' => 'direct']))->assertSessionHasErrors(['quotation_id' => 'Choose the quotation this PO is for.']);
        $rejected = $this->quotation($party);
        $rejected->forceFill(['status' => QuotationStatus::Rejected->value, 'status_reason' => 'Price'])->save();
        $store($this->crmPayload($rejected))->assertSessionHasErrors(['quotation_id' => $rejected->activityLabel().' is Rejected. Set its customer status to Accepted, or Revise it, before entering a PO.']);
        $old = $this->quotation($party);
        $this->actingAs($admin)->post(route('crm.quotations.revise', $old));
        $old->refresh();
        $this->assertFalse((bool) $old->is_latest);
        $store($this->crmPayload($old))->assertSessionHasErrors(['quotation_id' => $old->activityLabel().' is an earlier revision. A PO can be entered only against the current revision of a quotation sent to the customer.']);
        $this->assertSame(0, CustomerPo::count());

        // Saved on q1: client and enquiry from it, value typed, Accepted, PO received.
        $store($this->crmPayload($q1))->assertSessionHasNoErrors();
        $po = CustomerPo::firstOrFail();
        $this->assertSame([(int) $party->id, (int) $q1->enquiry_id, '455.00', 'crm'], [(int) $po->party_id, (int) $po->enquiry_id, (string) $po->po_value, $po->source]);
        $this->assertSame(QuotationStatus::Accepted, $q1->fresh()->status);
        $this->assertSame(EnquiryStatus::PoReceived, Enquiry::find($q1->enquiry_id)->status);
        // The number once per enquiry.
        $store($this->crmPayload($q1))->assertSessionHasErrors(['po_no' => 'PO / agreement KP/PO/1 is already entered for this enquiry.']);
        // The quotation cannot be cleared, nor moved to a Draft; moved to q2, both enquiries are refreshed.
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $po), $this->crmPayload(''))->assertSessionHasErrors(['quotation_id' => 'Choose the quotation this PO is for.']);
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $po), $this->crmPayload($draft))
            ->assertSessionHasErrors(['quotation_id' => $draft->activityLabel().' is Draft. A PO can be entered only against the current revision of a quotation sent to the customer.']);
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q2))->assertSessionHasNoErrors()->assertRedirect(route('crm.customer-pos.show', $po));
        $po->refresh();
        $this->assertSame([(int) $q2->id, (int) $q2->enquiry_id, 'crm'], [(int) $po->quotation_id, (int) $po->enquiry_id, $po->source]);
        $this->assertSame(EnquiryStatus::Open, Enquiry::find($q1->enquiry_id)->status);
        $this->assertSame(EnquiryStatus::PoReceived, Enquiry::find($q2->enquiry_id)->status);
        $this->assertSame(QuotationStatus::Accepted, $q2->fresh()->status);
        // The same PO no on another enquiry is fine (per enquiry, as before), even for the same customer.
        $q3 = $this->quotation($party);
        $store($this->crmPayload($q3))->assertSessionHasNoErrors();
        $this->assertSame(2, CustomerPo::where('po_no', 'KP/PO/1')->count());
        // The CRM step links to the PO; the list and CSV show both.
        $this->actingAs($admin)->get(route('crm.customer-pos.list'))->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 2)->assertDontSee('Direct PO');
        $this->assertStringContainsString('KP/PO/1', $this->actingAs($admin)->get(route('crm.customer-pos.export'))->assertOk()->streamedContent());
        // Delete opens the lead again.
        $this->actingAs($admin)->delete(route('crm.customer-pos.destroy', $po))->assertRedirect(route('crm.customer-pos.index'));
        $this->assertSame(EnquiryStatus::Open, Enquiry::find($q2->enquiry_id)->status);

        // A prospect's quotation gives a PO for the prospect (no customer), as before.
        $pq = $this->quotation(null);
        $store($this->crmPayload($pq, ['po_no' => 'PR/1']))->assertSessionHasNoErrors();
        $ppo = CustomerPo::where('po_no', 'PR/1')->firstOrFail();
        $this->assertNull($ppo->party_id);
        $this->assertSame((int) $pq->prospect_id, (int) $ppo->prospect_id);
        $this->actingAs($admin)->get(route('crm.customer-pos.show', $ppo))->assertOk()->assertSee($pq->prospect->name);
    }

    public function test_items_on_a_crm_po_follow_the_quotation(): void
    {
        $party = $this->customer();
        $other = $this->customer('Orion Agro');
        $own = $this->item($party, 'PL-OWN', 100);
        $common = $this->item(null, 'PL-ANY', 50);
        $foreign = $this->item($other, 'PL-ORION', 70);
        $bought = $this->item($party, 'PL-BUY', 10, 'Purchase');
        $inactive = $this->item($party, 'PL-OLD', 10, 'Sales', 'Inactive');
        $admin = $this->admin();
        $q = $this->quotation($party);
        $pq = $this->quotation(null);

        // Party quotation: its customer's items and items of no party; another customer's, a purchase or an inactive item refused.
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($q, [], [$this->line($own, 1), $this->line($foreign, 1), $this->line($bought, 1), $this->line($inactive, 1)]))
            ->assertSessionHasErrors(['lines.1.item_id' => 'Item 2 is not an active sales item of this customer.', 'lines.2.item_id' => 'Item 3 is not an active sales item of this customer.',
                'lines.3.item_id' => 'Item 4 is not an active sales item of this customer.']);
        // With items the value is their sum, even when a bad value is typed; a blank rate is the item's price.
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['po_value' => 'abc'], [$this->line($own, 2), $this->line($common, 1, '50')]))->assertSessionHasNoErrors();
        $po = CustomerPo::latest('id')->first();
        $this->assertSame(['250.00', ['200.00', '50.00']], [(string) $po->po_value, $po->lines->pluck('amount')->all()]);
        $ids = collect($this->actingAs($admin)->getJson(route('crm.customer-pos.items', ['quotation' => $q->id]))->json())->pluck('id')->sort()->values()->all();
        $this->assertSame([$own->id, $common->id], $ids);
        $this->actingAs($admin)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('PL-OWN')->assertSee('Items (Part master)');
        // A forged customer does not widen the items.
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['po_no' => 'X/2', 'party_id' => $other->id], [$this->line($foreign, 1)]))
            ->assertSessionHasErrors('lines.0.item_id');
        // Prospect's quotation: any sales item (as the picker offers), never a purchase item.
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($pq, ['po_no' => 'PR/1'], [$this->line($bought, 1)]))->assertSessionHasErrors('lines.0.item_id');
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($pq, ['po_no' => 'PR/1'], [$this->line($foreign, 1), $this->line($own, 1)]))->assertSessionHasNoErrors();
        $this->assertSame('170.00', (string) CustomerPo::where('po_no', 'PR/1')->first()->po_value);
        $this->assertCount(3, $this->actingAs($admin)->getJson(route('crm.customer-pos.items', ['quotation' => $pq->id]))->json());
        // No quotation chosen: no items.
        $this->assertSame([], $this->actingAs($admin)->getJson(route('crm.customer-pos.items', ['quotation' => 0]))->json());
        $this->assertSame([], $this->actingAs($admin)->getJson(route('crm.customer-pos.items'))->json());

        // Page setup: items mandatory, or hidden (then not read, so the saved items stay and the value is typed).
        $this->setCrmLines(true, true);
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q, ['lines_json' => '[]']))->assertSessionHasErrors('lines');
        $this->setCrmLines(false, false);
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q, ['po_value' => '999'], [$this->line($foreign, 1)]))->assertSessionHasNoErrors();
        $this->assertSame([2, '999.00'], [$po->lines()->count(), (string) $po->fresh()->po_value]);
        $this->actingAs($admin)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('data-crm-lines="0"', false);
    }

    public function test_item_limits_tampered_rows_and_line_ids(): void
    {
        $apex = $this->customer('Apex Auto Components');
        $kaveri = $this->customer('Kaveri Metals', 'Both');
        $sheet = $this->item(null, 'RM-CR-1.6', 68, 'Both');
        $dear = $this->item($apex, 'DEAR-1', 99999999999);
        $kavPart = $this->item($kaveri, 'KAV-100', 100);
        $admin = $this->admin();
        $q = $this->quotation($apex);
        $post = fn (array $lines, array $over = []) => $this->actingAs($admin)->from(route('crm.customer-pos.create'))->post(route('crm.customer-pos.store'), $this->crmPayload($q, $over, $lines));

        // decimal(15,2): a line, a blank rate (the item price) or the total too large is refused, not a database error.
        $post([$this->line($sheet, '999999999999', '99999999999')])->assertSessionHasErrors('lines.0.quantity');
        $post([$this->line($dear, '1000')])->assertSessionHasErrors('lines.0.quantity');
        $big = $this->line($sheet, '1000000000', '6000');
        $post([$big, $big])->assertSessionHasErrors('lines');
        // Quantities that round to nothing, negative or text rates, too many items, bad item ids.
        $post([$this->line($sheet, '0.0004')])->assertSessionHasErrors('lines.0.quantity');
        $post([$this->line($sheet, '-1')])->assertSessionHasErrors('lines.0.quantity');
        $post([$this->line($sheet, '1', '-5')])->assertSessionHasErrors('lines.0.rate');
        $post([$this->line($sheet, '1', 'abc')])->assertSessionHasErrors('lines.0.rate');
        $post(array_fill(0, 201, $this->line($sheet, '1')))->assertSessionHasErrors('lines');
        $post([$this->line($sheet, '1', '', ['item_id' => '-3'])])->assertSessionHasErrors('lines.0.item_id');
        // A tampered row comes back with a message, not an error page.
        $this->followingRedirects()->actingAs($admin)->from(route('crm.customer-pos.create'))
            ->post(route('crm.customer-pos.store'), $this->crmPayload($q, [], [$this->line($sheet, '', '', ['quantity' => ['5'], 'description' => ['x' => 'y'], 'rate' => ['1']])]))
            ->assertOk()->assertSee('Check the fields marked in red.')->assertSee('Enter the quantity.');
        $this->followingRedirects()->actingAs($admin)->from(route('crm.customer-pos.create'))
            ->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['po_value' => ''], [['item_id' => ['1'], 'description' => ['x'], 'quantity' => ['2'], 'rate' => ['3'], 'uom_id' => ['4'], 'id' => ['5']]]))
            ->assertOk()->assertSee('Add at least one item, or enter the PO value.');
        $this->assertSame(0, CustomerPo::count());
        // Just within the limit is saved.
        $post([$this->line($sheet, '1000000000', '9999.99')])->assertSessionHasNoErrors();
        $this->assertSame('9999990000000.00', CustomerPo::firstOrFail()->po_value);

        // A line id of another PO is not taken over.
        $kq = $this->quotation($kaveri);
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), $this->crmPayload($kq, ['po_no' => 'KAV/1'], [$this->line($kavPart, 2)]))->assertSessionHasNoErrors();
        [$apexPo, $kavPo] = CustomerPo::orderBy('id')->get()->all();
        $kavLine = $kavPo->lines()->firstOrFail();
        $this->actingAs($admin)->put(route('crm.customer-pos.update', $apexPo), $this->crmPayload($q, [], [$this->line($sheet, 1, '', ['id' => (string) $kavLine->id])]))->assertSessionHasNoErrors();
        $this->assertSame($kavPo->id, $kavLine->fresh()->customer_po_id);
        $this->assertSame(['RM-CR-1.6'], $apexPo->lines()->with('item')->get()->map(fn ($l) => $l->item->item_no)->all());
        $this->assertSame('200.00', $kavPo->fresh()->po_value);
    }

    public function test_crm_page_security_units_forged_fields_and_lookups(): void
    {
        $party = $this->customer();
        $other = $this->customer('Orion Agro');
        $admin = $this->admin();
        $sales = $this->sales();
        $clerk = $this->poClerk();
        $q = $this->quotation($party);
        $q2 = $this->quotation($party);
        $mine = $this->crmEnquiry(['party_id' => $other->id, 'prospect_id' => '']);
        $unit9 = Unit::create(['code' => 'U9', 'name' => 'Unit 9']);
        $foreignQ = $this->quotation($party);
        $foreignQ->forceFill(['unit_id' => $unit9->id])->save();
        Enquiry::whereKey($foreignQ->enquiry_id)->update(['unit_id' => $unit9->id]);

        $this->actingAs($clerk)->post(route('crm.customer-pos.store'), $this->crmPayload($foreignQ))
            ->assertSessionHasErrors(['quotation_id' => 'Choose a quotation made from an enquiry of your units.']);
        $this->actingAs($clerk)->get(route('crm.customer-pos.create', ['quotation' => $foreignQ->id]))->assertOk()->assertViewHas('po', fn ($po) => $po->quotation_id === null);
        $this->assertSame([], $this->actingAs($clerk)->getJson(route('crm.customer-pos.items', ['quotation' => $foreignQ->id]))->json());
        // Forged customer, enquiry, unit, source and type are ignored: everything comes from the quotation.
        $this->actingAs($clerk)->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['party_id' => $other->id, 'enquiry_id' => $mine->id,
            'unit_id' => $unit9->id, 'source' => 'direct', 'po_type' => 'direct', 'prospect_id' => 999, 'created_by' => 999]))->assertSessionHasNoErrors()->assertRedirect();
        $po = CustomerPo::latest('id')->firstOrFail();
        $this->assertSame([(int) $party->id, (int) $q->enquiry_id, (int) Unit::first()->id, 'crm', null, (int) $clerk->id],
            [(int) $po->party_id, (int) $po->enquiry_id, (int) $po->unit_id, $po->source, $po->prospect_id, (int) $po->created_by]);
        $this->assertSame(EnquiryStatus::Open, $mine->fresh()->status);
        // Tampered quotation ids give messages, not errors.
        foreach (['12abc', ['1'], '-1', '0'] as $bad) {
            $this->actingAs($clerk)->put(route('crm.customer-pos.update', $po), $this->crmPayload($bad))->assertSessionHasErrors('quotation_id');
        }
        $this->actingAs($clerk)->post(route('crm.customer-pos.store'), $this->crmPayload($q->id.'abc', ['po_no' => 'T/1']))->assertSessionHasErrors(['quotation_id' => 'Choose a quotation from the list.']);
        // Moving to another unit's quotation is refused; to another sent one of the unit, allowed.
        $this->actingAs($clerk)->put(route('crm.customer-pos.update', $po), $this->crmPayload($foreignQ))
            ->assertSessionHasErrors(['quotation_id' => 'Choose a quotation made from an enquiry of your units.']);
        $this->actingAs($clerk)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q2))->assertSessionHasNoErrors();

        // Another unit's PO: 403 everywhere (before its fields are checked), never listed.
        $theirs = (new CustomerPo(['po_no' => 'U9/1', 'po_date' => '2026-10-10', 'po_value' => 1, 'received_date' => '2026-10-10', 'received_by' => $this->crmEmployee()->id]))
            ->forceFill(['unit_id' => $unit9->id, 'quotation_id' => $foreignQ->id, 'enquiry_id' => $foreignQ->enquiry_id, 'party_id' => $party->id, 'source' => 'crm',
                'financial_year_id' => \App\Models\FinancialYear::forDate(now())->id]);
        $theirs->save();
        foreach ([$clerk, $sales] as $u) {
            $this->actingAs($u)->get(route('crm.customer-pos.show', $theirs))->assertForbidden();
            $this->actingAs($u)->get(route('crm.customer-pos.index', ['id' => $theirs->id]))->assertRedirect(route('crm.customer-pos.show', $theirs->id));
            $this->actingAs($u)->put(route('crm.customer-pos.update', $theirs), ['po_no' => ''])->assertForbidden();
            $this->actingAs($u)->delete(route('crm.customer-pos.destroy', $theirs))->assertForbidden();
            $this->assertNotContains('U9/1', collect($this->actingAs($u)->getJson(route('crm.customer-pos.lookup', ['q' => 'U9']))->json())->pluck('label')->all());
            $this->assertStringNotContainsString('U9/1', $this->actingAs($u)->get(route('crm.customer-pos.export'))->assertOk()->streamedContent());
            $this->assertSame(1, $this->actingAs($u)->get(route('crm.customer-pos.list'))->assertOk()->viewData('rows')->total());
        }
        $this->assertNotNull($theirs->fresh());
        // Lookups: no enquiry lookup on PO rights alone; the quotation lookup only this unit.
        $this->actingAs($clerk)->getJson(route('crm.enquiries.lookup'))->assertForbidden();
        $ids = collect($this->actingAs($clerk)->getJson(route('crm.quotations.lookup', ['q' => $foreignQ->quotation_no]))->json())->pluck('id')->all();
        $this->assertNotContains($foreignQ->id, $ids);
        // A PO-rights-only user sees no PO pending alert (it opens the quotation list).
        $this->assertFalse(collect(app(\App\Services\CrmAlerts::class)->for($clerk, Unit::first()))->contains('label', 'PO pending'));
        $this->assertTrue($admin->can('customer-po.view'));
    }

    public function test_a_user_with_only_po_rights_and_separate_add_and_edit_rights(): void
    {
        $party = $this->customer();
        $palm = $this->item($party, 'PL-ARECA', 1450);
        $q = $this->quotation($party);
        $clerk = $this->poClerk();

        $bar = $this->bar($clerk);
        $this->assertSame(['6 PO / Agreement'], $bar['crm']);
        $this->assertArrayNotHasKey('po', $bar);
        $this->actingAs($clerk)->get(route('crm.customer-pos.create', ['quotation' => $q->id]))->assertOk()
            ->assertSee('name="quotation_id"', false)->assertViewHas('po', fn ($po) => (int) $po->quotation_id === (int) $q->id);
        $this->actingAs($clerk)->getJson(route('crm.quotations.lookup'))->assertOk();
        $this->actingAs($clerk)->getJson(route('crm.customer-pos.items', ['quotation' => $q->id]))->assertOk()->assertJsonCount(1);
        $this->actingAs($clerk)->post(route('crm.customer-pos.store'), $this->crmPayload($q, [], [$this->line($palm, 1)]))->assertSessionHasNoErrors();
        $po = CustomerPo::latest('id')->firstOrFail();
        $this->actingAs($clerk)->get(route('crm.customer-pos.show', $po))->assertOk();
        $this->actingAs($clerk)->get(route('crm.customer-pos.list'))->assertOk()->assertSee('KP/PO/1');
        // The rest of CRM stays closed.
        $this->actingAs($clerk)->get('/crm/enquiries')->assertForbidden();
        $this->actingAs($clerk)->get(route('crm.quotations.show', $q))->assertForbidden();

        // Edit without Add, and Add without Edit.
        $editor = $this->user('poeditor', ['customer-po.view', 'customer-po.edit'], 'po.editor@company.example', '9822300001');
        $this->actingAs($editor)->get(route('crm.customer-pos.create'))->assertForbidden();
        $this->actingAs($editor)->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['po_no' => 'E/1']))->assertForbidden();
        $this->actingAs($editor)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('Save changes');
        $this->actingAs($editor)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q, [], [$this->line($palm, 2)]))->assertSessionHasNoErrors();
        $this->assertSame('2900.00', $po->fresh()->po_value);
        $this->actingAs($editor)->delete(route('crm.customer-pos.destroy', $po))->assertForbidden();
        $adder = $this->user('poadder', ['customer-po.view', 'customer-po.add'], 'po.adder@company.example', '9822300002');
        $this->actingAs($adder)->get(route('crm.customer-pos.show', $po))->assertOk()->assertDontSee('Save changes');
        $this->actingAs($adder)->put(route('crm.customer-pos.update', $po), $this->crmPayload($q, [], [$this->line($palm, 9)]))->assertForbidden();
    }

    public function test_documents_saved_with_a_new_po(): void
    {
        Storage::fake('local');
        $q = $this->quotation($this->customer());
        $sales = $this->sales();
        $this->actingAs($sales)->post(route('crm.customer-pos.store'), $this->crmPayload($q, ['doc_category' => 'Customer PO',
            'doc_files' => [UploadedFile::fake()->create('po.pdf', 10, 'application/pdf')]]))->assertSessionHasNoErrors()
            ->assertSessionHas('toast', 'PO / agreement KP/PO/1 saved with 1 document');
        $po = CustomerPo::firstOrFail();
        $doc = Document::where('documentable_id', $po->id)->firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('PO / Agreement documents')->assertSee('po.pdf');
        $this->actingAs($sales)->get(route('crm.documents.download', $doc))->assertOk();
        // A wrong file type: nothing is saved.
        $this->actingAs($sales)->post(route('crm.customer-pos.store'), $this->crmPayload($this->quotation($this->customer('Orion Agro')), ['po_no' => 'X/1', 'doc_category' => 'Customer PO',
            'doc_files' => [UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')]]))->assertSessionHasErrors('doc_files.0');
        $this->assertSame(1, CustomerPo::count());
    }

    public function test_items_and_uoms_used_on_a_po_cannot_be_deleted(): void
    {
        $party = $this->customer();
        $palm = $this->item($party, 'PL-ARECA', 1450);
        $box = Uom::create(['code' => 'BOX', 'description' => 'Box', 'is_active' => true]);
        $q = $this->quotation($party);
        $this->actingAs($this->admin())->post(route('crm.customer-pos.store'), $this->crmPayload($q, [], [$this->line($palm, 1, '', ['uom_id' => (string) $box->id])]))->assertSessionHasNoErrors();
        $this->assertSame($box->id, CustomerPoLine::firstOrFail()->uom_id);
        try {
            app(\App\Services\SalesMasterService::class)->delete('uoms', $box, $this->admin());
            $this->fail('A UOM used on a PO item was deleted.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->errors()['delete'][0]);
        }
        $this->assertNotNull(Uom::find($box->id));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\ItemService::class)->delete($palm, $this->admin());
    }
}
