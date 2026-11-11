<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContactService;
use App\Services\CrmAlerts;
use App\Services\ModuleSettings;
use App\Services\PageSetupService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Fixes from the four reviews of Page setup, optional parts and Contact / POC (14/10/2026). Test database only. */
class PageSetupReviewFixesTest extends TestCase
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
        return $this->user('priya@company.example', 'Sales');
    }

    private function user(string $email, ?string $role, array $perms = []): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98221'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        if ($role) {
            $u->assignRole($role);
        }
        if ($perms) {
            $u->givePermissionTo($perms);
        }
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function switch(array $modules, ?User $as = null)
    {
        // CRM is a switch too (01/11/2026), on by default; the Separate PO menu switch was removed (02/11/2026).
        $all = array_merge(['crm' => true, 'site_visit' => true, 'boq' => true, 'costing' => true], $modules);

        return $this->actingAs($as ?? $this->admin())->put('/admin/page-setup-modules', ['modules' => array_map(fn ($on) => $on ? '1' : '0', $all)]);
    }

    private function hide(string $page, array $fields, array $mandatory = []): void
    {
        $choices = [];
        foreach (app(PageSetupService::class)->fields($page) as $f => $cur) {
            $choices[$f] = ['visible' => ! in_array($f, $fields, true), 'required' => in_array($f, $mandatory, true) || $cur['required']];
        }
        app(PageSetupService::class)->save($page, $choices, $this->admin());
    }

    // ---- Architecture P1: a switched-off part is refused in one place, the gate, for every role ----

    public function test_p1_the_gate_refuses_every_right_of_a_switched_off_part_for_every_role_and_route(): void
    {
        $this->switch(['site_visit' => false])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $admin = $this->admin();
        foreach (['view', 'add', 'edit', 'delete'] as $a) {
            $this->assertFalse($admin->can("site-visit.$a"));
            $this->assertFalse($this->sales()->can("site-visit.$a"));
        }
        $this->assertTrue($admin->can('enquiry.view'));
        $this->assertTrue($this->sales()->can('enquiry.view'));

        // Walk every route that names a site visit right: a site-visit-only route answers "switched off" (404),
        // a route that also accepts another right refuses a user who holds only the site visit right (403).
        $visitOnly = $this->user('raj@company.example', null, ['site-visit.view', 'site-visit.add', 'site-visit.edit', 'site-visit.delete']);
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $perms = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
                ->flatMap(fn ($m) => explode('|', substr($m, 11)))->values();
            if (! $perms->contains(fn ($p) => str_starts_with($p, 'site-visit.'))) {
                continue;
            }
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $onlyVisit = $perms->every(fn ($p) => str_starts_with($p, 'site-visit.'));
            $res = $this->actingAs($onlyVisit ? $admin : $visitOnly)->call($method, $uri);
            $this->assertSame($onlyVisit ? 404 : 403, $res->getStatusCode(), "$method $uri");
            $checked++;
        }
        $this->assertGreaterThanOrEqual(10, $checked);

        // the enquiry search no longer answers a site-visit-only user (the reviewer's example)
        $this->actingAs($visitOnly)->getJson('/crm/enquiries/lookup?q=E')->assertForbidden();
        $this->switch(['site_visit' => true]);
        app()->forgetScopedInstances();
        $this->actingAs($visitOnly)->getJson('/crm/enquiries/lookup?q=E')->assertOk();
    }

    // ---- Code review M1: hidden "Lead assigned to" ----

    public function test_m1_hidden_lead_assigned_to_gives_the_lead_to_the_person_adding_it(): void
    {
        $sales = $this->sales();
        $emp = Employee::create(['code' => 'E777', 'name' => 'Priya', 'status' => 'Active', 'user_id' => $sales->id]);
        $this->hide('enquiry', ['assigned_to']);
        $payload = $this->enquiryPayload();
        unset($payload['assigned_to']);
        $this->actingAs($sales)->get('/crm/enquiries/create')->assertOk()->assertDontSee('id="assigned_to"', false);
        $this->actingAs($sales)->post('/crm/enquiries', $payload)->assertSessionHasNoErrors();
        $e = Enquiry::latest('id')->firstOrFail();
        $this->assertSame($emp->id, $e->assigned_to);
        $due = app(CrmAlerts::class)->due($sales, Unit::first());
        $this->assertSame(1, $due['count']);
        $this->assertSame($e->enquiry_no, $due['list'][0]['no']);

        // an edit keeps the saved assignee
        $this->actingAs($sales)->put("/crm/enquiries/{$e->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame($emp->id, $e->fresh()->assigned_to);
    }

    // ---- UX review M1 and M3, code review S1 and S2 ----

    public function test_ux_m1_switching_off_asks_first_and_the_toast_says_the_new_state(): void
    {
        $this->actingAs($this->admin())->get('/admin/page-setup')->assertOk()
            ->assertSee('data-off="Everyone loses the Site visit page and list', false)->assertSee('When off:')->assertSee("confirm(msg)", false)
            // the message's line breaks are written as backslash-n, so the script stays one valid statement
            ->assertSee('.join(\' and \')+\'?\n\n\'', false);
        $this->switch(['site_visit' => false, 'boq' => false])->assertSessionHas('toast', 'Site Visit / Requirement Gathering and BOQ Preparation switched off · CRM and Cost details (costing) on'); // CRM is a switch since 01/11/2026; no Separate PO menu since 02/11/2026
    }

    public function test_ux_m3_a_switched_off_part_says_so_instead_of_page_not_found(): void
    {
        Storage::fake('local');
        $q = $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload());
        $q = Quotation::firstOrFail();
        $this->actingAs($this->admin())->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')]);
        $this->switch(['site_visit' => false, 'boq' => false]);
        app()->forgetScopedInstances();

        $this->actingAs($this->sales())->get('/crm/site-visits/5')->assertNotFound()
            ->assertSee('Site Visit / Requirement Gathering is switched off')->assertSee('Saved entries are kept')->assertSee('Go to Enquiry')->assertDontSee('record was deleted');
        $this->actingAs($this->admin())->get('/crm/documents/'.Document::first()->id)->assertNotFound()->assertSee('BOQ Preparation is switched off');
        // a record that really is missing still says "Page not found"
        $this->actingAs($this->sales())->get('/crm/quotations/999')->assertNotFound()->assertSee('Page not found');
    }

    public function test_code_s1_s2_costing_switch_needs_approval_edit_and_every_part_must_be_sent(): void
    {
        $office = Role::create(['name' => 'Office', 'guard_name' => 'web']);
        $office->givePermissionTo(['page-setup.view', 'page-setup.edit']);
        $clerk = $this->user('clerk@company.example', 'Office');

        $this->switch(['costing' => false], $clerk)->assertSessionHasErrors('modules.costing');
        $this->assertTrue(app(ModuleSettings::class)->on('costing'));
        $this->switch(['site_visit' => false], $clerk)->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->assertFalse(app(ModuleSettings::class)->on('site_visit'));
        $this->actingAs($clerk)->get('/admin/page-setup')->assertOk()->assertSee('name="modules[costing]" value="1"', false);

        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => ['site_visit' => '1']])->assertSessionHasErrors(['modules.boq', 'modules.costing']);
        $this->actingAs($this->admin())->put('/admin/page-setup-modules', [])->assertSessionHasErrors('modules');
        app()->forgetScopedInstances();
        $this->assertTrue(app(ModuleSettings::class)->on('boq'));
    }

    public function test_arch_s1_a_save_writes_the_new_values_into_the_cache(): void
    {
        app(PageSetupService::class)->fields('contact');
        app(ModuleSettings::class)->all();
        $this->switch(['boq' => false]);
        $this->assertSame(['crm' => true, 'site_visit' => true, 'boq' => false, 'costing' => true], Cache::get('module-settings')); // crm added 01/11/2026 (po removed 02/11/2026)
        $this->hide('contact', ['notes']);
        $this->assertSame([false, false], Cache::get('page-setup.contact')['notes']);
    }

    // ---- UX review M2 and S8: back to the enquiry or prospect after adding a contact ----

    public function test_ux_m2_adding_a_contact_from_an_enquiry_returns_to_it_with_name_and_mobile_filled(): void
    {
        $sales = $this->sales();
        $e = $this->crmEnquiry();
        $from = 'enquiry:'.$e->id;
        $this->actingAs($sales)->get("/crm/enquiries/{$e->id}")->assertSee(route('crm.contacts.create', ['prospect' => $e->prospect_id, 'from' => $from]));

        $form = $this->actingAs($sales)->get("/crm/contacts/create?prospect={$e->prospect_id}&from=$from")->assertOk();
        $form->assertSee('Back to '.$e->enquiry_no)->assertSee('value="Kavita Rao"', false)->assertSee('value="+91 98220 11111"', false)->assertSee('name="from" value="'.$from.'"', false);
        $this->actingAs($sales)->post('/crm/contacts', ['prospect_id' => $e->prospect_id, 'name' => 'Kavita Rao', 'mobile' => '9822011111', 'from' => $from])
            ->assertRedirect(route('crm.enquiries.show', $e).'#contacts')->assertSessionHas('toast', 'Contact '.Contact::where('name', 'Kavita Rao')->value('contact_no').' Kavita Rao added');

        // the second contact starts empty; editing from the enquiry also comes back to it
        $this->actingAs($sales)->get("/crm/contacts/create?prospect={$e->prospect_id}&from=$from")->assertDontSee('value="Kavita Rao"', false);
        $c = Contact::firstOrFail();
        $this->actingAs($sales)->put("/crm/contacts/{$c->id}", ['prospect_id' => $e->prospect_id, 'name' => 'Kavita R', 'mobile' => '9822011111', 'from' => $from])
            ->assertRedirect(route('crm.enquiries.show', $e).'#contacts');

        // from the prospect page
        $p = $e->prospect;
        $this->actingAs($sales)->post('/crm/contacts', ['prospect_id' => $p->id, 'name' => 'Ajay', 'mobile' => '9822011112', 'from' => 'prospect:'.$p->id])
            ->assertRedirect(route('crm.prospects.show', $p).'#contacts');

        // anything else in "from" is ignored, so it can never send the user elsewhere
        foreach (['https://evil.example', 'enquiry:999', 'user:1', '/admin/users'] as $bad) {
            $this->actingAs($sales)->post('/crm/contacts', ['prospect_id' => $p->id, 'name' => 'X '.$bad, 'mobile' => '9822011113', 'from' => $bad])
                ->assertRedirect(route('crm.contacts.show', Contact::latest('id')->first()));
        }
    }

    // ---- Smaller suggestions ----

    public function test_suggestions_contacts_prospect_delete_inactive_customer_and_enquiry_delete_message(): void
    {
        $admin = $this->admin();
        // DB PS-S3: deleting a prospect takes its contacts with it, logged
        $p = Prospect::create(['name' => 'Zen Gardens', 'market' => 'Domestic', 'phone_1' => '9822099999']);
        app(ContactService::class)->create(['prospect_id' => $p->id, 'name' => 'Meera', 'mobile' => '9822099990'], $admin);
        $this->actingAs($admin)->delete("/crm/prospects/{$p->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, Contact::count());
        $this->assertSame(1, Contact::withTrashed()->count());
        $this->assertStringContainsString('and its 1 contact(s)', ActivityLog::where('action', 'prospect.deleted')->value('description'));

        // code S4: no new contact for an inactive customer
        $inactive = Party::create(['name' => 'Old Co', 'type' => 'Customer', 'status' => 'Inactive']);
        $this->actingAs($admin)->post('/crm/contacts', ['party_id' => $inactive->id, 'name' => 'A', 'mobile' => '9822011111'])->assertSessionHasErrors('party_id');

        // code S3: an enquiry with a site visit, while site visits are off, says how to reach it
        $e = $this->crmEnquiry();
        $this->actingAs($admin)->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'status' => 'Planned']);
        $this->switch(['site_visit' => false]);
        $this->actingAs($admin)->delete("/crm/enquiries/{$e->id}")->assertSessionHasErrors(['delete' => $e->enquiry_no.' has 1 site visit(s), so it cannot be deleted. Site visits are switched off; an administrator can switch them on in Admin › Page setup to remove them.']);
    }

    public function test_suggestions_costs_left_out_of_the_log_and_quotation_import_follows_page_setup(): void
    {
        $sales = $this->sales();
        // arch S4: while costing is off, a new quotation's log has no cost lines
        $this->switch(['costing' => false]);
        $this->actingAs($sales)->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();
        $log = ActivityLog::where('action', 'quotation.created')->firstOrFail();
        $this->assertEmpty(array_filter(array_keys($log->changes['fields']), fn ($k) => str_starts_with($k, 'Cost:')));
        $this->switch(['costing' => true]);

        // arch S5: import reads the quotation Page setup (Notes hidden, Payment terms mandatory)
        $this->hide('quotation', ['notes'], ['payment_terms']);
        $enq = Enquiry::firstOrFail();
        $head = implode(',', array_values(QuotationService::IMPORT_COLUMNS));
        // Columns as IMPORT_COLUMNS (23/10/2026): no "GST / tax" column; Unit is a UOM master code, Tax a Tax master name or rate.
        $row = fn ($pay) => implode(',', ['1', $enq->enquiry_no, '05/10/2026', '', '', '', $sales->email, 'INR', $pay, '', '', 'Imported note', '', 'Areca palm', '2', 'Nos', '100', '', '']);
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => UploadedFile::fake()->createWithContent('q.csv', $head."\n".$row('')."\n")])->assertSessionHasErrors('file');
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => UploadedFile::fake()->createWithContent('q.csv', $head."\n".$row('50% advance')."\n")])->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame('50% advance', $q->payment_terms);
        $this->assertNull($q->notes);
        $this->assertSame('NOS', $q->lines()->value('uom'));
    }

    // ---- Detailed test S1: Recent changes leaves out what belongs to a switched-off part ----

    public function test_detailed_s1_recent_changes_hide_boq_cost_and_site_visit_rows_while_off(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq-plan.pdf', 10, 'application/pdf')]);
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'Other', 'file' => UploadedFile::fake()->create('site-map.pdf', 10, 'application/pdf')]);
        // a change of the cost details only, then a change of the header
        $payload = $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id]);
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", array_merge($payload, ['cost' => ['material_cost' => '250', 'labour_cost' => '100', 'margin_percent' => '20']]))->assertSessionHasNoErrors();
        $costOnly = \App\Models\ActivityLog::where('action', 'quotation.updated')->latest('id')->firstOrFail();
        $this->assertTrue(collect(array_keys($costOnly->changes))->every(fn ($k) => str_starts_with($k, 'Cost: ')));
        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertSee('>boq-plan.pdf</a>', false)->assertViewHas('history', fn ($h) => $h->contains('id', $costOnly->id));

        $this->switch(['boq' => false, 'costing' => false, 'site_visit' => false])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $page = $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk();
        $page->assertDontSee('boq-plan.pdf')->assertSee('site-map.pdf')
            ->assertViewHas('history', fn ($h) => ! $h->contains('id', $costOnly->id) && $h->isNotEmpty());

        // site visit rows never show on a record's history while site visits are off
        $this->switch(['site_visit' => true]);
        app()->forgetScopedInstances();
        $e = $q->enquiry;
        $this->actingAs($admin)->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'status' => 'Planned'])->assertSessionHasNoErrors();
        $visit = \App\Models\SiteVisit::firstOrFail();
        $this->assertCount(1, \App\Models\ActivityLog::history($visit));
        $this->switch(['site_visit' => false, 'boq' => false, 'costing' => false]);
        app()->forgetScopedInstances();
        $this->assertCount(0, \App\Models\ActivityLog::history($visit));

        // switched back on, everything shows again; Admin › Activity log always keeps every row
        $this->switch([]);
        app()->forgetScopedInstances();
        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertSee('>boq-plan.pdf</a>', false)->assertViewHas('history', fn ($h) => $h->contains('id', $costOnly->id));
        $this->assertSame(1, \App\Models\ActivityLog::where('description', 'like', '%boq-plan.pdf%')->count());
    }

    // ---- Architecture S3: every page in Page setup has a Form Request that applies it ----

    public function test_arch_s3_every_page_in_page_setup_is_applied_by_a_form_request(): void
    {
        $code = collect(\Illuminate\Support\Facades\File::allFiles(app_path()))->map(fn ($f) => $f->getContents())->implode("\n");
        foreach (array_keys(config('nursery.page_fields')) as $page) {
            $direct = str_contains($code, "rules('$page'");
            $master = str_starts_with($page, 'master-') && str_contains($code, "rules('master-'.");
            $this->assertTrue($direct || $master, "No PageSetupService::rules() call for page \"$page\"");
        }
    }
}
