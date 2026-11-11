<?php

namespace Tests\Feature;

use App\Jobs\SendQuotationMail;
use App\Mail\QuotationMail;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\LeadSource;
use App\Models\ModuleSetting;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModuleSettings;
use App\Services\PageSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Page setup for every page, switches for the optional CRM parts (Site visit, BOQ, costing) and Contact / POC
 * (Sat, 14/10/2026). Runs on the test database only (SQLite in memory, or a throwaway copy).
 */
class PageSetupAndModulesTest extends TestCase
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
        return $this->userWithRole('Sales', 'priya@company.example');
    }

    private function userWithRole(string $role, string $email): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98220'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** Field => [visible, required] for the page setup form; fields not given keep their current choice. */
    private function setUp_(string $page, array $choices): void
    {
        $fields = [];
        foreach (app(PageSetupService::class)->fields($page) as $f => $cur) {
            [$vis, $req] = $choices[$f] ?? [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put("/admin/page-setup/$page", ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function switch(array $modules): void
    {
        // CRM is a switch too (01/11/2026), on by default; the Separate PO menu switch was removed (02/11/2026).
        $all = array_merge(['crm' => true, 'site_visit' => true, 'boq' => true, 'costing' => true], $modules);
        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => array_map(fn ($on) => $on ? '1' : '0', $all)])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/page-setup');
    }

    private function visit(Enquiry $e, array $over = []): SiteVisit
    {
        $this->actingAs($this->admin())->post('/crm/site-visits', array_merge([
            'enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'visit_time' => '11:30', 'status' => 'Planned', 'poc' => 'Mr Shah', 'floors' => 'Ground + 2',
        ], $over))->assertSessionHasNoErrors();

        return SiteVisit::latest('id')->first();
    }

    private function quotation(array $over = []): Quotation
    {
        $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload($over))->assertSessionHasNoErrors();

        return Quotation::latest('id')->first();
    }

    // ---- Page setup for every page ----

    public function test_page_setup_lists_every_crm_page_and_master_and_each_opens(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/page-setup')->assertOk()->getContent();
        foreach (['Optional parts', 'CRM', 'Masters', 'Enquiry', 'Site visit', 'Quotation', 'PO / Agreement received', 'Prospect', 'Contact / POC',
            'Employee master', 'Source master', 'Category master', 'Industry type master', 'City master'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (array_keys(config('nursery.page_fields')) as $page) {
            $this->actingAs($this->admin())->get("/admin/page-setup/$page")->assertOk()->assertSee('Save page setup');
        }
        $this->actingAs($this->admin())->get('/admin/page-setup/nothing')->assertNotFound();
    }

    public function test_a_page_added_later_plugs_in_through_configuration_only(): void
    {
        config(['nursery.page_fields.example-later-page' => ['label' => 'Example later page', 'group' => 'Accounts', 'fields' => [
            'grn_date' => ['label' => 'GRN date', 'required' => true, 'fixed' => true],
            'po_ref' => ['label' => 'Customer PO ref', 'required' => false],
            'remarks' => ['label' => 'Remarks', 'required' => true],
        ]]]);
        $setup = app(PageSetupService::class);
        $this->assertSame(['example-later-page' => 'Example later page'], $setup->pages()['Accounts']);

        $rules = $setup->rules('example-later-page', ['grn_date' => ['required', 'date_format:d/m/Y'], 'po_ref' => ['nullable', 'string'], 'remarks' => ['string'], 'other' => ['nullable']]);
        $this->assertSame(['required', 'date_format:d/m/Y'], $rules['grn_date']);
        $this->assertSame(['nullable', 'string'], $rules['po_ref']);
        $this->assertSame(['required', 'string'], $rules['remarks']);
        $this->assertSame(['nullable'], $rules['other']);

        $setup->save('example-later-page', ['po_ref' => ['visible' => true, 'required' => true], 'remarks' => ['visible' => false, 'required' => true]], $this->admin());
        $rules = $setup->rules('example-later-page', ['po_ref' => ['nullable', 'string'], 'remarks' => ['string'], 'remarks.*' => ['string']]);
        $this->assertSame(['required', 'string'], $rules['po_ref']);
        $this->assertArrayNotHasKey('remarks', $rules);
        $this->assertArrayNotHasKey('remarks.*', $rules);
        $this->assertSame('Mandatory: GRN date and Customer PO ref.', $setup->form('example-later-page')->mandatory());
        $this->assertFalse($setup->form('example-later-page')->show('remarks'));
    }

    public function test_site_visit_fields_follow_page_setup_and_hidden_values_are_kept(): void
    {
        $e = $this->crmEnquiry();
        $v = $this->visit($e);
        $this->setUp_('site-visit', ['poc' => [false, false], 'visit_time' => [false, false], 'floors' => [true, true]]);

        $form = $this->actingAs($this->admin())->get("/crm/site-visits/{$v->id}")->assertOk();
        $form->assertDontSee('POC (person on site)')->assertDontSee('id="visit_time"', false)->assertSee('Mandatory: Client (enquiry), Visit date, Status and Floors.');

        $this->actingAs($this->admin())->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '09/10/2026', 'status' => 'Planned'])->assertSessionHasErrors('floors');
        $this->actingAs($this->admin())->put("/crm/site-visits/{$v->id}", ['enquiry_id' => $e->id, 'visit_date' => '10/10/2026', 'status' => 'Done', 'floors' => 'Ground', 'poc' => 'Changed'])
            ->assertSessionHasNoErrors();
        $v->refresh();
        $this->assertSame('Mr Shah', $v->poc); // hidden: kept, not overwritten by what was posted
        $this->assertSame('10/10/2026 11:30', $v->visit_at->format('d/m/Y H:i')); // hidden time kept
        $this->assertSame('Ground', $v->floors);
    }

    public function test_quotation_header_fields_follow_page_setup(): void
    {
        $q = $this->quotation();
        $this->setUp_('quotation', ['site' => [false, false], 'payment_terms' => [true, true]]);
        $this->actingAs($this->sales())->get("/crm/quotations/{$q->id}")->assertOk()->assertDontSee('id="site"', false)->assertSee('Payment terms<span class="req">*</span>', false);

        $payload = $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id, 'site' => 'Other site', 'payment_terms' => '']);
        $this->actingAs($this->sales())->put("/crm/quotations/{$q->id}", $payload)->assertSessionHasErrors('payment_terms');
        $payload['payment_terms'] = '100% advance';
        $this->actingAs($this->sales())->put("/crm/quotations/{$q->id}", $payload)->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('Tower B lobby', $q->site);
        $this->assertSame('100% advance', $q->payment_terms);
    }

    public function test_po_prospect_employee_and_master_pages_follow_page_setup(): void
    {
        $admin = $this->admin();
        // PO: Customer contact mandatory, validity hidden
        $q = $this->quotation();
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/sent");
        $this->setUp_('customer-po', ['contact_name' => [true, true], 'validity' => [false, false]]);
        $po = ['quotation_id' => $q->id, 'po_no' => 'KP/PO/9', 'po_date' => '10/10/2026', 'po_value' => '455', 'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026',
            'valid_from' => '01/11/2026', 'valid_to' => '01/10/2026'];
        $this->actingAs($admin)->post('/crm/pos', $po)->assertSessionHasErrors('contact_name')->assertSessionDoesntHaveErrors('valid_to');
        $this->actingAs($admin)->post('/crm/pos', $po + ['contact_name' => 'Kavita'])->assertSessionHasNoErrors();
        $this->assertNull(CustomerPo::firstOrFail()->valid_from);
        $this->actingAs($admin)->get('/crm/pos/'.CustomerPo::first()->id)->assertOk()->assertDontSee('Validity start date');

        // Prospect: Position mandatory, Email hidden; the page, the Add prospect drawer and imports all follow it
        $this->setUp_('prospect', ['position' => [true, true], 'email' => [false, false]]);
        $this->actingAs($admin)->postJson('/crm/prospects/quick', ['name' => 'Asha Interiors', 'market' => 'Domestic', 'phone_1' => '9822012000'])->assertJsonValidationErrors('position');
        $this->actingAs($admin)->postJson('/crm/prospects/quick', ['name' => 'Asha Interiors', 'market' => 'Domestic', 'phone_1' => '9822012000', 'position' => 'Owner', 'email' => 'a@b.example'])->assertCreated();
        $this->assertNull(Prospect::where('name', 'Asha Interiors')->value('email'));
        $csv = "Prospect name,Domestic / Export,Contact person,Position,Contact no 1,Contact no 2,Email,GSTIN,Industry type,Address\nNilgiri Farms,Domestic,,,9822013000,,,,,\n";
        $this->actingAs($admin)->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertSessionHasErrors('file');
        $this->actingAs($admin)->get('/crm/prospects/create')->assertOk()->assertDontSee('id="email"', false)->assertSee('Mandatory: Prospect name, Domestic / Export, Position and Contact no 1.');

        // Employee master: Mobile mandatory, Date of joining hidden and kept
        $emp = $this->crmEmployee();
        $emp->forceFill(['joining_date' => '2024-04-01'])->save();
        $this->setUp_('employee', ['mobile' => [true, true], 'joining_date' => [false, false]]);
        $data = ['code' => 'E001', 'name' => 'Ravi Kumar', 'status' => 'Active', 'joining_date' => '01/01/2020'];
        $this->actingAs($admin)->put("/admin/masters/employees/{$emp->id}", $data)->assertSessionHasErrors('mobile');
        $this->actingAs($admin)->put("/admin/masters/employees/{$emp->id}", $data + ['mobile' => '9822000001'])->assertSessionHasNoErrors();
        $this->assertSame('2024-04-01', $emp->fresh()->joining_date->toDateString());

        // Source master: Order in dropdowns hidden, so a new entry goes last and an edit keeps the order
        $this->setUp_('master-sources', ['sort_order' => [false, false]]);
        $last = (int) LeadSource::max('sort_order');
        $this->actingAs($admin)->post('/admin/masters/sources', ['name' => 'Trade fair', 'sort_order' => '1', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($last + 1, (int) LeadSource::where('name', 'Trade fair')->value('sort_order'));
        $this->setUp_('master-cities', ['sort_order' => [true, true]]);
        $this->actingAs($admin)->post('/admin/masters/cities', ['name' => 'Nashik', 'is_active' => '1'])->assertSessionHasErrors('sort_order');
    }

    public function test_fixed_and_show_only_fields_cannot_be_hidden_or_made_mandatory(): void
    {
        $this->setUp_('contact', ['name' => [false, false], 'mobile' => [true, false], 'roles' => [true, true]]);
        $f = app(PageSetupService::class)->fields('contact');
        $this->assertTrue($f['name']['visible'] && $f['name']['required']);
        $this->assertFalse($f['mobile']['required']);
        $this->assertTrue($f['roles']['visible']);
        $this->assertFalse($f['roles']['required']);
        $this->actingAs($this->admin())->get('/admin/page-setup/contact')->assertSee('Cannot be mandatory');
    }

    public function test_page_setup_and_switches_need_page_setup_edit_and_are_logged(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales)->get('/admin/page-setup')->assertForbidden();
        $this->actingAs($sales)->put('/admin/page-setup-modules', ['modules' => ['site_visit' => '0']])->assertForbidden();
        $this->actingAs($sales)->put('/admin/page-setup/site-visit', ['fields' => []])->assertForbidden();
        $this->assertTrue(app(ModuleSettings::class)->on('site_visit'));

        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => ['site_visit' => '0', 'payroll' => '1']])->assertSessionHasErrors('modules');
        $this->switch(['costing' => false]);
        $log = ActivityLog::where('action', 'modules.updated')->latest('id')->firstOrFail();
        $this->assertSame(['old' => 'On', 'new' => 'Off'], $log->changes['Cost details (costing)']);
        $this->assertSame(1, ModuleSetting::where('module', 'costing')->where('is_enabled', false)->count());
        $this->actingAs($this->admin())->get('/admin/page-setup')->assertOk()->assertSee('Save optional parts');
    }

    public function test_switches_and_page_setup_are_read_from_the_cache_not_on_every_request(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales)->get('/crm/enquiries/create')->assertOk(); // warms the cache
        app()->forgetScopedInstances();
        DB::enableQueryLog();
        $this->actingAs($sales)->get('/crm/enquiries/create')->assertOk();
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        $this->assertStringNotContainsString('module_settings', $sql);
        $this->assertStringNotContainsString('page_settings', $sql);

        // A save clears the cache, so the next request sees the change at once.
        $this->switch(['site_visit' => false]);
        app()->forgetScopedInstances();
        $this->actingAs($sales)->get('/crm/site-visits')->assertNotFound();
    }

    // ---- Switches: Site Visit / Requirement Gathering ----

    public function test_site_visit_switched_off_disappears_everywhere_and_comes_back_with_its_data(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $sales = $this->sales();
        $e = $this->crmEnquiry(['next_action_date' => today()->format('d/m/Y')]);
        $v = $this->visit($e, ['visit_date' => today()->format('d/m/Y')]);
        $this->actingAs($admin)->post("/crm/documents/site_visit/{$v->id}", ['category' => 'Site photo', 'file' => UploadedFile::fake()->create('site.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();
        $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertOk()->assertSee('Site visits due');

        $this->switch(['site_visit' => false]);

        // pages, lists, lookups and its documents are refused
        foreach (['/crm/site-visits', '/crm/site-visits/list', '/crm/site-visits/create', "/crm/site-visits/{$v->id}", '/crm/site-visits/lookup', "/crm/documents/{$doc->id}"] as $url) {
            $this->actingAs($sales)->get($url)->assertNotFound();
        }
        $this->actingAs($sales)->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '09/10/2026', 'status' => 'Planned'])->assertNotFound();
        $this->actingAs($sales)->put("/crm/site-visits/{$v->id}", ['enquiry_id' => $e->id, 'visit_date' => '09/10/2026', 'status' => 'Done'])->assertNotFound();
        $this->actingAs($admin)->delete("/crm/site-visits/{$v->id}")->assertNotFound();

        // menu, bell, enquiry workflow, Access rights, Page setup and the lead report
        $page = $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertOk();
        $page->assertDontSee(route('crm.site-visits.index'))->assertDontSee('Site visits due')
            ->assertViewHas('flow', fn ($steps) => ! in_array('Site visit', array_column($steps, 'label'), true) && count($steps) === 5);
        $this->actingAs($admin)->get('/admin/roles/'.Role::findByName('Sales')->id.'/edit')->assertOk()->assertDontSee('value="site-visit.view"', false)->assertSee('value="enquiry.view"', false);
        $this->actingAs($admin)->get('/admin/page-setup')->assertOk()->assertDontSee(route('admin.page-setup.edit', 'site-visit'));
        $this->actingAs($admin)->get('/admin/page-setup/site-visit')->assertNotFound();
        $range = ['from' => '01/04/2026', 'to' => '31/03/2027', 'format' => 'csv'];
        $csv = $this->actingAs($admin)->get('/reports/crm/lead-report/download?'.http_build_query($range))->streamedContent();
        $this->assertStringNotContainsString('Site visit', $csv);
        $this->assertStringContainsString('Follow-ups', $csv);

        // the data is kept, and shows again when switched back on
        $this->assertSame(1, SiteVisit::count());
        $this->switch(['site_visit' => true]);
        $this->actingAs($sales)->get("/crm/site-visits/{$v->id}")->assertOk()->assertSee('Mr Shah');
        $this->actingAs($sales)->get("/crm/documents/{$doc->id}")->assertOk();
        $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertSee('Site visits due')->assertViewHas('flow', fn ($s) => count($s) === 6);
        $csv = $this->actingAs($admin)->get('/reports/crm/lead-report/download?'.http_build_query($range))->streamedContent();
        $this->assertStringContainsString('Last site visit', $csv);
    }

    public function test_saving_a_role_or_user_while_a_part_is_off_keeps_its_rights(): void
    {
        $sales = Role::findByName('Sales');
        $this->switch(['site_visit' => false]);
        $sent = array_values(array_filter($sales->permissions->pluck('name')->all(), fn ($p) => ! str_starts_with($p, 'site-visit.')));
        $this->actingAs($this->admin())->put("/admin/roles/{$sales->id}", ['name' => 'Sales', 'permissions' => array_values(array_diff($sent, ['prospect.delete']))])->assertSessionHasNoErrors();
        $perms = $sales->fresh()->permissions->pluck('name')->all();
        $this->assertContains('site-visit.add', $perms);
        $this->assertNotContains('prospect.delete', $perms);

        // a user's extra right on a hidden page is kept too
        $purchase = $this->userWithRole('Purchase', 'raj@company.example');
        $purchase->givePermissionTo(['site-visit.view']);
        $this->actingAs($this->admin())->put("/admin/users/{$purchase->id}", [
            'name' => $purchase->name, 'email' => $purchase->email, 'mobile' => $purchase->mobile, 'role' => 'Purchase', 'status' => 'Active',
            'units' => [Unit::first()->id], 'permissions' => ['enquiry.view'],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['site-visit.view', 'enquiry.view'], $purchase->fresh()->getDirectPermissions()->pluck('name')->all());
    }

    // ---- Switches: Cost details and BOQ ----

    public function test_costing_switched_off_hides_cost_details_and_submit_needs_no_costs(): void
    {
        $q = $this->quotation(['cost' => ['material_cost' => '200', 'margin_percent' => '10']]);
        $this->switch(['costing' => false]);

        $this->actingAs($this->sales())->get("/crm/quotations/{$q->id}")->assertOk()->assertDontSee('id="costBox"', false)->assertDontSee('with its costing', false);
        // cost values posted anyway are not read; the saved costs are kept
        $this->actingAs($this->sales())->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id, 'cost' => ['material_cost' => '999']]))->assertSessionHasNoErrors();
        $this->assertSame('200.00', $q->fresh()->cost->material_cost);

        $q2 = $this->quotation(['cost' => []]);
        $this->assertNull($q2->cost);
        $this->actingAs($this->sales())->post("/crm/quotations/{$q2->id}/submit")->assertSessionHasNoErrors();
        $this->assertNotSame('Draft', $q2->fresh()->status->value);

        $this->assertSame('Quotation and BOQ', \App\Support\Access::pageLabel(config('nursery.pages.CRM.quotation')));
        $this->assertSame('BOQ, quotation', \App\Support\CrmFlow::quotationStep());
        $this->actingAs($this->admin())->get('/admin/approval')->assertSee('BOQ + quotation (with Rev no)');

        $this->switch(['costing' => true]);
        $this->actingAs($this->sales())->get("/crm/quotations/{$q->id}")->assertSee('Cost details')->assertSee('value="200"', false);
        $this->assertSame('Quotation, BOQ and costing', \App\Support\Access::pageLabel(config('nursery.pages.CRM.quotation')));
    }

    public function test_boq_switched_off_hides_boq_documents_and_leaves_them_out_of_the_email(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $q = $this->quotation();
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $boq = Document::firstOrFail();
        $this->switch(['boq' => false]);

        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk()->assertSee('Attachments')->assertDontSee('>boq.pdf</a>', false)->assertDontSee('<option>BOQ</option>', false);
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('b2.pdf', 10, 'application/pdf')])->assertSessionHasErrors('category');
        $this->actingAs($admin)->get("/crm/documents/{$boq->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/crm/documents/{$boq->id}")->assertNotFound();
        $this->assertSame('Costing, quotation', \App\Support\CrmFlow::quotationStep());

        Queue::fake();
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/email", ['to' => 'buyer@kaveri.example'])->assertSessionHasNoErrors();
        Mail::fake();
        (new SendQuotationMail($q->id, 'buyer@kaveri.example', null, null, $admin->id))->handle();
        Mail::assertSent(QuotationMail::class, fn (QuotationMail $m) => count($m->attachments()) === 1);

        $this->switch(['boq' => true]);
        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertSee('BOQ and attachments')->assertSee('>boq.pdf</a>', false);
        $this->assertSame(1, Document::count());
    }

    // ---- Contact / POC ----

    public function test_contacts_are_added_numbered_logged_and_shown_on_the_enquiry_and_prospect(): void
    {
        $sales = $this->sales();
        $prospect = $this->prospectFixture();
        $this->actingAs($sales)->post('/crm/contacts', ['name' => 'Kavita Rao'])->assertSessionHasErrors(['prospect_id', 'mobile']);
        $this->actingAs($sales)->post('/crm/contacts', ['name' => 'Kavita Rao', 'prospect_id' => $prospect->id, 'party_id' => '', 'mobile' => '12'])->assertSessionHasErrors('mobile');
        $this->actingAs($sales)->post('/crm/contacts', [
            'prospect_id' => $prospect->id, 'party_id' => '', 'name' => 'Kavita Rao', 'designation' => 'Admin head', 'department' => 'Facilities',
            'mobile' => '+91 98220 11111', 'whatsapp' => '9822011111', 'email' => 'Kavita@Kaveri.example', 'alternate_no' => '', 'preferred_communication' => 'WhatsApp',
            'is_decision_maker' => '1', 'is_influencer' => '0', 'is_purchase_contact' => '0', 'is_facility_contact' => '1', 'is_billing_contact' => '0', 'notes' => 'Call after 11',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $c = Contact::firstOrFail();
        $this->assertSame('CT'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT), $c->contact_no); // from the row id
        $this->assertSame('kavita@kaveri.example', $c->email);
        $this->assertSame('9822011111', $c->mobile_digits);
        $this->assertSame('Decision maker, Facility contact', $c->roleNames());
        $this->assertSame('contact.created', ActivityLog::where('subject_type', 'contact')->value('action'));
        $this->actingAs($sales)->post('/crm/contacts', ['name' => 'Ajay', 'prospect_id' => $prospect->id, 'mobile' => '9822011112', 'preferred_communication' => 'Fax'])->assertSessionHasErrors('preferred_communication');

        // on the enquiry and prospect pages, with the client's link
        $e = $this->crmEnquiry();
        $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertOk()->assertSee('Contacts / POC')->assertSee('Kavita Rao')->assertSee(route('crm.contacts.create', ['prospect' => $prospect->id]), false);
        $this->actingAs($sales)->get("/crm/prospects/{$prospect->id}")->assertOk()->assertSee('Contacts / POC')->assertSee($c->contact_no);
        $this->actingAs($sales)->get('/crm/contacts/create?prospect='.$prospect->id)->assertOk()->assertSee('<option value="'.$prospect->id.'" selected>', false);

        // edit and delete are logged
        $this->actingAs($sales)->put("/crm/contacts/{$c->id}", ['prospect_id' => $prospect->id, 'name' => 'Kavita Rao', 'mobile' => '9822011111', 'designation' => 'GM'])->assertSessionHasNoErrors();
        $this->assertSame(['old' => 'Admin head', 'new' => 'GM'], ActivityLog::where('action', 'contact.updated')->firstOrFail()->changes['Designation']);
        $this->actingAs($sales)->delete("/crm/contacts/{$c->id}")->assertRedirect('/crm/contacts?prospect='.$prospect->id);
        $this->assertSoftDeleted($c);
        $this->assertSame(1, ActivityLog::where('action', 'contact.deleted')->count());
    }

    public function test_contact_list_is_paged_and_searched_on_the_server(): void
    {
        $prospect = $this->prospectFixture();
        $other = Prospect::create(['name' => 'Zen Gardens', 'market' => 'Domestic', 'phone_1' => '9822099999']);
        for ($i = 1; $i <= 30; $i++) {
            app(\App\Services\ContactService::class)->create(['prospect_id' => $prospect->id, 'name' => sprintf('Person %02d', $i), 'mobile' => '98220'.sprintf('%05d', $i)], $this->admin());
        }
        app(\App\Services\ContactService::class)->create(['prospect_id' => $other->id, 'name' => 'Meera', 'mobile' => '+91 90110 22334'], $this->admin());
        $sales = $this->sales();

        $this->actingAs($sales)->get('/crm/contacts')->assertOk()->assertViewHas('rows', fn ($p) => $p->count() === 25 && $p->total() === 31);
        $this->actingAs($sales)->get('/crm/contacts?per_page=100&sort=no&dir=desc')->assertViewHas('rows', fn ($p) => $p->count() === 31 && $p->first()->name === 'Meera');
        $this->actingAs($sales)->get('/crm/contacts?q=Meer')->assertViewHas('rows', fn ($p) => $p->total() === 1);
        $this->actingAs($sales)->get('/crm/contacts?q='.urlencode('9011022'))->assertViewHas('rows', fn ($p) => $p->total() === 1 && $p->first()->name === 'Meera');
        $this->actingAs($sales)->get('/crm/contacts?q='.Contact::where('name', 'Meera')->value('contact_no'))->assertViewHas('rows', fn ($p) => $p->total() === 1);
        $this->actingAs($sales)->get('/crm/contacts?q=Zen')->assertViewHas('rows', fn ($p) => $p->total() === 1);
        $this->actingAs($sales)->get('/crm/contacts?prospect='.$other->id)->assertOk()->assertSee('Contacts of Zen Gardens')->assertViewHas('rows', fn ($p) => $p->total() === 1);
        $this->actingAs($sales)->get('/crm/contacts?q=100%25')->assertViewHas('rows', fn ($p) => $p->total() === 0);

        // the enquiry box shows the first 10 by name and links to the rest
        $e = $this->crmEnquiry();
        $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertViewHas('contactBox', fn ($b) => $b['list']->count() === 10 && $b['more'] === true);
    }

    public function test_contact_rights_menu_and_page_setup(): void
    {
        $prospect = $this->prospectFixture();
        $c = app(\App\Services\ContactService::class)->create(['prospect_id' => $prospect->id, 'name' => 'Kavita', 'mobile' => '9822011111', 'notes' => 'Keep me'], $this->admin());

        // a role without Contact rights: no menu item, pages refused, no box on the prospect
        $purchase = $this->userWithRole('Purchase', 'raj@company.example');
        $this->actingAs($purchase)->get('/crm/contacts')->assertForbidden();
        $this->actingAs($purchase)->get("/crm/contacts/{$c->id}")->assertForbidden();
        $this->actingAs($purchase)->post('/crm/contacts', ['name' => 'X', 'prospect_id' => $prospect->id])->assertForbidden();
        $this->actingAs($this->sales())->get('/crm/enquiries')->assertSee(route('crm.contacts.index'));
        $this->assertTrue(Role::findByName('Sales')->hasPermissionTo('contact.delete'));

        // Page setup: Notes hidden (kept), Email mandatory
        $this->setUp_('contact', ['notes' => [false, false], 'email' => [true, true]]);
        $sales = $this->sales();
        $this->actingAs($sales)->get("/crm/contacts/{$c->id}")->assertOk()->assertDontSee('id="notes"', false)->assertSee('Mandatory: Company / Client, Name, Mobile and Email.');
        $this->actingAs($sales)->put("/crm/contacts/{$c->id}", ['prospect_id' => $prospect->id, 'name' => 'Kavita', 'mobile' => '9822011111', 'notes' => 'x'])->assertSessionHasErrors('email');
        $this->actingAs($sales)->put("/crm/contacts/{$c->id}", ['prospect_id' => $prospect->id, 'name' => 'Kavita', 'mobile' => '9822011111', 'email' => 'k@k.example', 'notes' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame('Keep me', $c->fresh()->notes);
    }
}
