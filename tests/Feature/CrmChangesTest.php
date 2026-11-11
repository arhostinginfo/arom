<?php

namespace Tests\Feature;

use App\Jobs\SendQuotationMail;
use App\Mail\QuotationMail;
use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\City;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\LeadSource;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** CRM changes requested by Sat on 13/10/2026 ("CRM changes.docx"), one group of tests per point of the document. */
class CrmChangesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function makeUser(string $role, string $email, string $mobile, ?array $units = null): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => $mobile, 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync($units ?? [Unit::first()->id]);

        return $u;
    }

    private function sales(): User
    {
        return User::where('email', 'priya@company.example')->first() ?? $this->makeUser('Sales', 'priya@company.example', '9822000010');
    }

    private function quotationPayload(array $over = []): array
    {
        return $this->newQuotationPayload($over);
    }

    private function saveQuotation(array $over = []): Quotation
    {
        $this->actingAs($this->admin())->post('/crm/quotations', $this->quotationPayload($over))->assertSessionHasNoErrors();

        return Quotation::latest('id')->first();
    }

    // ---- Source, Category, Industry type and City masters; Employee master ----

    public function test_masters_are_seeded_with_the_word_file_lists(): void
    {
        $this->assertSame(18, LeadSource::count());
        $this->assertTrue(LeadSource::where('name', 'Facility Management Company')->exists());
        $this->assertSame(12, ServiceCategory::count());
        $this->assertTrue(ServiceCategory::where('name', 'Vertical Garden / Green Wall')->exists());
        $this->assertTrue(\App\Models\IndustryType::where('name', 'Hospital')->exists());
        $this->assertTrue(City::where('name', 'Pune')->exists());
        $this->assertSame(100000.0, (float) ApprovalRule::for('quotation')->approve_above_amount);
    }

    public function test_simple_master_add_edit_delete_and_in_use_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/masters')->assertOk()->assertSee('Source master')->assertSee('Employee master')->assertSee('Party master');
        $this->actingAs($admin)->get('/admin/masters/sources?q=Face')->assertOk()->assertSee('Facebook')->assertDontSee('Website');

        $this->actingAs($admin)->post('/admin/masters/sources', ['name' => '  Trade   Fair ', 'sort_order' => 19, 'is_active' => 1])->assertRedirect('/admin/masters/sources');
        $src = LeadSource::where('name', 'Trade Fair')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['action' => 'master.created', 'subject_type' => 'lead_source', 'subject_id' => $src->id]);
        $this->actingAs($admin)->post('/admin/masters/sources', ['name' => 'trade fair'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put("/admin/masters/sources/{$src->id}", ['name' => 'Trade Fair', 'sort_order' => 19, 'is_active' => 0])->assertRedirect();
        $this->assertFalse($src->fresh()->is_active);
        // an inactive source is not offered on a new enquiry
        $this->actingAs($admin)->get('/crm/enquiries/create')->assertOk()->assertDontSee('>Trade Fair</option>', false)->assertSee('>Website</option>', false);

        // a source used on an enquiry cannot be deleted
        $website = LeadSource::where('name', 'Website')->first();
        $this->crmEnquiry();
        $this->actingAs($admin)->delete("/admin/masters/sources/{$website->id}")->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('lead_sources', ['id' => $website->id]);
        $this->actingAs($admin)->delete("/admin/masters/sources/{$src->id}")->assertRedirect();
        $this->assertDatabaseMissing('lead_sources', ['id' => $src->id]);

        $sales = $this->sales();
        $this->actingAs($sales)->get('/admin/masters')->assertForbidden();
        $this->actingAs($sales)->post('/admin/masters/cities', ['name' => 'Satara'])->assertForbidden();
        $this->actingAs($admin)->get('/admin/masters/unknown')->assertNotFound();
    }

    public function test_employee_master_add_edit_and_delete_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/masters/employees', [])->assertSessionHasErrors(['code', 'name', 'status']);
        $this->actingAs($admin)->post('/admin/masters/employees', [
            'code' => 'e-101', 'name' => 'Anita Desai', 'designation' => 'Horticulturist', 'department' => 'Projects', 'mobile' => '9822000101',
            'email' => 'ANITA@company.example', 'joining_date' => '01/06/2024', 'user_id' => $this->sales()->id, 'status' => 'Active',
        ])->assertRedirect();
        $e = Employee::where('code', 'E-101')->firstOrFail();
        $this->assertSame('anita@company.example', $e->email);
        $this->assertSame('2024-06-01', $e->joining_date->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'employee.created', 'subject_id' => $e->id]);

        // code and login user are unique
        $this->actingAs($admin)->post('/admin/masters/employees', ['code' => 'E-101', 'name' => 'X', 'status' => 'Active', 'user_id' => $this->sales()->id])
            ->assertSessionHasErrors(['code', 'user_id']);

        $this->actingAs($admin)->get('/admin/masters/employees?q=Ani')->assertOk()->assertSee('Anita Desai');
        $this->actingAs($admin)->put("/admin/masters/employees/{$e->id}", ['code' => 'E-101', 'name' => 'Anita Desai', 'status' => 'Inactive'])->assertRedirect();
        $this->assertSame('Inactive', $e->fresh()->status);

        // an employee a lead is assigned to cannot be deleted
        $this->crmEnquiry(['assigned_to' => $e->id]);
        $this->actingAs($admin)->delete("/admin/masters/employees/{$e->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted('employees', ['id' => $e->id]);
    }

    // ---- New Enquiry: fields, mandatory rules, Page setup ----

    public function test_enquiry_gets_a_number_and_needs_the_mandatory_fields(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/enquiries', [])->assertSessionHasErrors([
            'enquiry_date', 'lead_source_id', 'prospect_type', 'prospect_id', 'industry_type_id', 'city_id', 'contact_name', 'contact_no',
            'service_category_id', 'lead_rating', 'assigned_to', 'next_action', 'next_action_date',
        ]);
        $this->actingAs($admin)->post('/crm/enquiries', [])->assertSessionDoesntHaveErrors(['sub_source', 'competitor_details', 'industry', 'address', 'website', 'approx_requirement', 'estimated_value', 'expected_closing_date', 'remarks']);
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['website' => 'not a site', 'contact_no' => 'abc', 'enquiry_date' => '31/02/2026']))
            ->assertSessionHasErrors(['website', 'enquiry_date'])
            // with a Prospect the contact no is not read: it comes from the Prospect (22/10/2026)
            ->assertSessionDoesntHaveErrors(['contact_name', 'contact_no']);
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => '', 'party_id' => '', 'contact_name' => '', 'contact_no' => '']))
            ->assertSessionHasErrors(['contact_name', 'contact_no']);

        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload())->assertRedirect();
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['lead_rating' => 'Warm']));
        $fy = FinancialYear::forDate(\Illuminate\Support\Carbon::create(2026, 10, 5))->code;
        $first = Enquiry::orderBy('id')->first();
        $this->assertSame("ENQ/$fy/0001", $first->enquiry_no);
        $this->assertSame("ENQ/$fy/0002", Enquiry::orderByDesc('id')->value('enquiry_no'));
        $this->assertSame('Open', $first->status->value);
        $this->assertSame('https://www.kaveri.example', $first->website);
        $this->assertSame('150000.00', $first->estimated_value);
        $this->assertDatabaseHas('activity_logs', ['action' => 'enquiry.created', 'subject_type' => 'enquiry', 'subject_id' => $first->id]);

        $this->actingAs($admin)->get("/crm/enquiries/{$first->id}")->assertOk()->assertSee($first->enquiry_no)->assertSee('Kavita Rao')->assertSee('Follow-ups');
        $this->actingAs($admin)->put("/crm/enquiries/{$first->id}", $this->enquiryPayload(['lead_rating' => 'Cold']))->assertRedirect();
        $log = ActivityLog::where('action', 'enquiry.updated')->latest('id')->first();
        $this->assertSame(['old' => 'Hot', 'new' => 'Cold'], $log->changes['Lead rating']);
    }

    public function test_page_setup_hides_fields_and_changes_mandatory_ones(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/page-setup')->assertOk()->assertSee('Enquiry');
        $this->actingAs($admin)->get('/admin/page-setup/enquiry')->assertOk()->assertSee('Competitor details')->assertSee('always shown and mandatory');

        $fields = [];
        foreach (array_keys(config('nursery.page_fields.enquiry.fields')) as $f) {
            $default = config("nursery.page_fields.enquiry.fields.$f.required");
            $fields[$f] = ['visible' => 1, 'required' => $default ? 1 : 0];
        }
        $fields['industry_type_id'] = ['visible' => 0, 'required' => 1];   // hidden: cannot stay mandatory
        $fields['competitor_details'] = ['visible' => 1, 'required' => 1]; // now mandatory
        $fields['enquiry_date'] = ['visible' => 0, 'required' => 0];       // fixed: ignored
        $this->actingAs($admin)->put('/admin/page-setup/enquiry', ['fields' => $fields])->assertRedirect('/admin/page-setup/enquiry');
        $this->assertDatabaseHas('page_settings', ['page' => 'enquiry', 'field' => 'industry_type_id', 'is_visible' => false, 'is_required' => false]);
        $this->assertDatabaseMissing('page_settings', ['field' => 'enquiry_date']);
        $log = ActivityLog::where('action', 'page_setup.updated')->first();
        $this->assertSame(['old' => 'Shown, mandatory', 'new' => 'Hidden'], $log->changes['Industry type']);

        $this->actingAs($admin)->get('/crm/enquiries/create')->assertOk()->assertDontSee('id="industry_type_id"', false)->assertSee('Competitor details');
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['competitor_details' => '']))->assertSessionHasErrors('competitor_details');
        // a hidden field is not required and what is posted for it is ignored
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['competitor_details' => 'GreenScape', 'industry_type_id' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Enquiry::first()->industry_type_id);

        $this->actingAs($this->sales())->get('/admin/page-setup')->assertForbidden();
        $this->actingAs($admin)->put('/admin/page-setup/enquiry', ['fields' => ['made_up' => ['visible' => 1]]])->assertSessionHasErrors('fields');
    }

    // ---- Follow-up & activity management, red pop up, notifications ----

    public function test_follow_up_sets_next_action_and_due_ones_show_in_red(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry(['next_action_date' => now()->addDays(3)->format('d/m/Y')]);
        $this->actingAs($admin)->post("/crm/enquiries/{$e->id}/activities", ['activity_type' => 'Fax'])->assertSessionHasErrorsIn('followup', ['activity_type', 'details']);
        $this->actingAs($admin)->post("/crm/enquiries/{$e->id}/activities", ['activity_type' => 'Call', 'activity_date' => '06/10/2026', 'details' => 'Spoke to Kavita', 'followup_action' => 'Send photos'])
            ->assertSessionHasErrorsIn('followup', 'target_date');

        $yesterday = now()->subDay()->format('d/m/Y');
        $this->actingAs($admin)->post("/crm/enquiries/{$e->id}/activities", [
            'activity_type' => 'WhatsApp', 'activity_date' => now()->format('d/m/Y'), 'details' => 'Sent catalogue', 'followup_action' => 'Call for feedback', 'target_date' => $yesterday,
        ])->assertRedirect();
        $e->refresh();
        $this->assertSame('Call for feedback', $e->next_action);
        $this->assertTrue($e->isDue());
        $this->assertSame(1, $e->activities()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'enquiry.followup_added', 'subject_id' => $e->id]);

        // the red pop up and the bell on every page
        $page = $this->actingAs($admin)->get('/admin/users')->assertOk();
        $page->assertSee('id="duePop"', false)->assertSee('Follow-ups due')->assertSee($e->enquiry_no)->assertSee('1 day(s) late');
        $this->actingAs($admin)->get('/crm/enquiries/list?view=due')->assertOk()->assertSee($e->enquiry_no);
        $this->actingAs($admin)->get("/crm/enquiries/{$e->id}")->assertSee('Follow-up due')->assertSee('Sent catalogue');

        // a sales user linked to another employee does not get this lead in the pop up
        $sales = $this->sales();
        $this->crmEmployee(['code' => 'E002', 'name' => 'Priya S', 'user_id' => $sales->id]);
        $this->actingAs($sales)->get('/crm/enquiries')->assertOk()->assertDontSee('id="duePop"', false);
        $this->actingAs($sales)->get('/crm/enquiries/list?view=due')->assertOk()->assertDontSee($e->enquiry_no);
    }

    public function test_alerts_count_site_visits_due_quotations_pending_approval_and_po_pending(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry(['next_action_date' => now()->addDays(5)->format('d/m/Y')]);
        app(\App\Services\SiteVisitService::class)->create(['enquiry_id' => $e->id, 'visit_date' => now()->format('d/m/Y'), 'visit_time' => '00:01', 'status' => 'Planned'], $admin);
        $q = $this->saveQuotation(['lines' => [['description' => 'Large job', 'qty' => '1', 'rate' => '250000']]]);
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit")->assertSessionHasNoErrors();

        $alerts = app(\App\Services\CrmAlerts::class)->for($admin, Unit::first());
        $byLabel = collect($alerts['items'])->pluck('count', 'label');
        $this->assertSame(1, $byLabel['Site visits due']);
        $this->assertSame(1, $byLabel['Quotations pending approval']);
        $this->assertSame(0, $byLabel['PO pending']);
        $this->assertSame(0, $byLabel['Follow-ups due']);
        $this->actingAs($admin)->get('/crm/site-visits/list?view=due')->assertOk()->assertSee($e->enquiry_no);
        $this->actingAs($admin)->get('/crm/quotations/list?view=approval')->assertOk()->assertSee($q->quotation_no);
        // a user without approval rights gets no approval alert
        $this->assertArrayNotHasKey('Quotations pending approval', collect(app(\App\Services\CrmAlerts::class)->for($this->sales(), Unit::first())['items'])->pluck('count', 'label')->all());
    }

    // ---- Site visit / requirement gathering ----

    public function test_site_visit_is_saved_against_an_enquiry_with_photos(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $e = $this->crmEnquiry();
        $this->actingAs($admin)->get("/crm/site-visits/create?enquiry={$e->id}")->assertOk()->assertSee('value="Kavita Rao"', false)->assertSee('(from the Prospect)');
        $this->actingAs($admin)->post('/crm/site-visits', ['status' => 'Planned'])->assertSessionHasErrors(['enquiry_id', 'visit_date']);
        $this->actingAs($admin)->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'visit_time' => '25:00', 'status' => 'Planned'])->assertSessionHasErrors('visit_time');

        $this->actingAs($admin)->post('/crm/site-visits', [
            'enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'visit_time' => '11:30', 'status' => 'Done', 'poc' => 'Kavita Rao',
            'technical_employee_id' => $this->crmEmployee()->id, 'area_measurements' => 'Lobby 30 x 20 ft', 'floors' => 'Ground + 2',
            'indoor_outdoor_area' => 'Indoor', 'existing_plants' => 'None', 'lighting_condition' => 'Bright indirect', 'water_availability' => 'Tap',
            'access_restrictions' => 'After 7 pm', 'customer_requirement' => 'Green lobby', 'next_action' => 'Prepare BOQ',
        ])->assertRedirect();
        $v = SiteVisit::firstOrFail();
        $this->assertSame('2026-10-08 11:30:00', $v->visit_at->format('Y-m-d H:i:s'));
        $this->assertSame($e->unit_id, $v->unit_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'site_visit.created', 'subject_id' => $v->id]);

        $this->actingAs($admin)->post("/crm/documents/site_visit/{$v->id}", ['category' => 'Site photo', 'file' => UploadedFile::fake()->create('lobby.jpg', 50, 'image/jpeg')])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('site_visit', $doc->documentable_type);
        $this->assertSame($admin->id, $doc->uploaded_by);
        $this->assertNotNull($doc->created_at);
        Storage::disk('local')->assertExists($doc->path);

        // the enquiry with a site visit cannot be deleted
        $this->actingAs($admin)->delete("/crm/enquiries/{$e->id}")->assertSessionHasErrors('delete');
    }

    // ---- BOQ + costing + quotation, quotation fields and totals ----

    public function test_quotation_from_an_enquiry_works_out_totals_and_costing(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry();
        $this->actingAs($admin)->get("/crm/quotations/create?enquiry={$e->id}")->assertOk()->assertSee('Tower B, Hinjewadi Phase 1, Pune')->assertSee('Cost details');

        // Unit and Tax from the UOM and Tax masters (23/10/2026): GST 18% gives 18% for this in-state prospect.
        $q = $this->saveQuotation([
            'enquiry_id' => $e->id,
            'discount_amount' => '1,000',
            'lines' => [
                ['item_no' => 'PL-1', 'description' => 'Areca palm', 'qty' => '20', 'uom' => 'NOS', 'rate' => '1450', 'discount_percent' => '10', 'tax_structure_id' => $this->taxId('GST 18%')],
                ['item_no' => '', 'description' => 'Maintenance', 'qty' => '12', 'uom' => 'SET', 'rate' => '2500', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')],
            ],
            'cost' => ['material_cost' => '18000', 'plant_production_cost' => '2000', 'planter_cost' => '4000', 'labour_cost' => '3000',
                'transportation_cost' => '1000', 'installation_cost' => '1000', 'maintenance_cost' => '6000', 'other_cost' => '0', 'margin_percent' => '25'],
        ]);
        // 20 × 1450 − 10% + 18% = 30,798.00; 12 × 2500 + 18% = 35,400.00
        $this->assertSame(['30798.00', '35400.00'], $q->lines->pluck('amount')->all());
        $this->assertSame(['18.00', '18.00'], $q->lines->pluck('tax_percent')->all());
        $this->assertSame(['NOS', 'SET'], $q->lines->pluck('uom')->all());
        $this->assertSame('66198.00', $q->subtotal);
        $this->assertSame('65198.00', $q->grand_total);
        $this->assertSame($e->enquiry_no, $q->enquiry_no);
        $this->assertSame($e->prospect_id, $q->prospect_id);
        $this->assertSame('Website', $q->source_of_enquiry);
        $c = $q->cost;
        $this->assertSame('35000.00', $c->total_cost);
        $this->assertSame('8750.00', $c->margin_amount);
        $this->assertSame('43750.00', $c->proposed_selling_price);

        // a discount larger than the items total is refused
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->quotationPayload(['enquiry_id' => $e->id, 'discount_amount' => '999999']))->assertSessionHasErrors('discount_amount');
        // valid until before the date is refused
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->quotationPayload(['valid_until' => '01/10/2026']))->assertSessionHasErrors('valid_until');

        // the PDF prints the totals but never the internal cost details
        $html = view('pdf.quotation', ['q' => $q->fresh(['lines', 'currency', 'unit', 'salesPerson', 'preparer', 'prospect'])])->render();
        $this->assertStringContainsString('65,198.00', $html);
        $this->assertStringNotContainsString('Proposed selling price', $html);
        $this->assertStringNotContainsString('43,750.00', $html);
        $this->actingAs($admin)->get("/crm/quotations/{$q->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_boq_upload_is_optional_and_checked(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $q = $this->saveQuotation();
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('script.exe', 10)])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'Visiting card', 'file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')])->assertSessionHasErrors('category');
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.uploaded', 'subject_type' => 'quotation', 'subject_id' => $q->id]);
        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertSee('boq.xlsx')->assertSee($admin->name);
        $this->actingAs($admin)->get("/crm/documents/{$doc->id}")->assertOk();

        // a Purchase user cannot see quotation documents; a user of another unit cannot either
        $buyer = $this->makeUser('Purchase', 'buyer@company.example', '9822000020');
        $this->actingAs($buyer)->get("/crm/documents/{$doc->id}")->assertForbidden();
        $other = $this->makeUser('Sales', 'other@company.example', '9822000021', [Unit::orderByDesc('id')->first()->id]);
        $this->actingAs($other)->get("/crm/documents/{$doc->id}")->assertForbidden();
        $this->actingAs($other)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertForbidden();

        $this->actingAs($admin)->delete("/crm/documents/{$doc->id}")->assertRedirect();
        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
        Storage::disk('local')->assertMissing($doc->path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.deleted']);
    }

    // ---- Approval workflow (amount criteria) and quotation status ----

    public function test_submit_below_the_limit_is_approved_at_once_and_above_waits_for_an_approver(): void
    {
        $admin = $this->admin();
        $small = $this->saveQuotation(); // 455.00, below 1,00,000
        $this->actingAs($admin)->post("/crm/quotations/{$small->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Approved', $small->fresh()->status->value);
        $this->assertDatabaseHas('approvals', ['approvable_type' => 'quotation', 'approvable_id' => $small->id, 'action' => 'Approved automatically']);

        $big = $this->saveQuotation(['lines' => [['description' => 'Vertical garden', 'qty' => '1', 'rate' => '150000']]]);
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Internal Approval', $big->fresh()->status->value);
        $this->actingAs($admin)->get("/crm/quotations/{$big->id}")->assertSee('Approve')->assertSee('Reject');

        // a draft cannot be changed once submitted
        $this->actingAs($admin)->put("/crm/quotations/{$big->id}", $this->quotationPayload(['site' => 'Changed']))->assertSessionHasErrors('status');

        // Sales cannot approve; an approver can, or can reject with a reason back to Draft
        $sales = $this->sales();
        $this->actingAs($sales)->post("/crm/quotations/{$big->id}/approve")->assertForbidden();
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/reject", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/reject", ['remarks' => 'Margin too low'])->assertSessionHasNoErrors();
        $big->refresh();
        $this->assertSame('Draft', $big->status->value);
        $this->assertStringContainsString('Margin too low', $big->status_reason);
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/submit");
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/approve", ['remarks' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $big->fresh()->status->value);
        $this->assertSame(['Submitted', 'Rejected', 'Submitted', 'Approved'], $big->approvals()->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.status_changed', 'subject_id' => $big->id]);

        // approving twice is refused
        $this->actingAs($admin)->post("/crm/quotations/{$big->id}/approve")->assertSessionHasErrors('status');
    }

    public function test_cost_details_are_optional_to_submit_and_the_rule_is_set_in_admin(): void
    {
        $admin = $this->admin();
        // Cost details are optional (23/10/2026): a quotation without them can still be submitted.
        $q = $this->saveQuotation(['cost' => []]);
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit")->assertSessionHasNoErrors();
        $this->assertNotSame('Draft', $q->fresh()->status->value);

        $this->actingAs($admin)->get('/admin/approval')->assertOk()->assertSee('Approve only above');
        $this->actingAs($admin)->put('/admin/approval', ['is_required' => '1', 'approve_above_amount' => 'abc'])->assertSessionHasErrors('approve_above_amount');
        $this->actingAs($admin)->put('/admin/approval', ['is_required' => '1', 'approve_above_amount' => ''])->assertRedirect('/admin/approval');
        $this->assertNull(ApprovalRule::for('quotation')->approve_above_amount);
        $this->assertDatabaseHas('activity_logs', ['action' => 'approval.updated']);

        // blank amount = every quotation needs approval
        $q2 = $this->saveQuotation();
        $this->actingAs($admin)->post("/crm/quotations/{$q2->id}/submit");
        $this->assertSame('Internal Approval', $q2->fresh()->status->value);
        $this->actingAs($this->sales())->put('/admin/approval', ['is_required' => '0'])->assertForbidden();
    }

    public function test_sent_by_email_with_boq_and_customer_status_changes_the_lead(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = $this->admin();
        $q = $this->saveQuotation();
        // the BOQ is added while the quotation is a Draft, and goes for approval with it
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/email", ['to' => 'buyer@kaveri.example'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Accepted'])->assertSessionHasErrors('status');

        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/email", ['to' => 'not-an-email'])->assertSessionHasErrors('to');
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/email", ['to' => 'buyer@kaveri.example', 'cc' => 'a@b.example, bad'])->assertSessionHasErrors('cc');
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/email", ['to' => 'buyer@kaveri.example', 'cc' => 'boss@kaveri.example'])->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('Sent', $q->status->value);
        $this->assertNotNull($q->sent_at);
        Queue::assertPushed(SendQuotationMail::class, fn ($job) => $job->quotationId === $q->id && $job->to === 'buyer@kaveri.example');

        // the job sends the PDF and the BOQ
        Mail::fake();
        (new SendQuotationMail($q->id, 'buyer@kaveri.example', 'boss@kaveri.example', 'Please see attached', $admin->id))->handle();
        Mail::assertSent(QuotationMail::class, function (QuotationMail $m) {
            $names = array_map(fn ($a) => $a->as, $m->attachments());

            return $m->hasTo('buyer@kaveri.example') && $m->hasCc('boss@kaveri.example') && count($names) === 2 && in_array('boq.pdf', $names, true);
        });

        // customer statuses; Rejected, Expired and Hold need a reason; Rejected loses the lead
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Hold'])->assertSessionHasErrors('status_reason');
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Negotiation'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Rejected', 'status_reason' => 'Budget cut'])->assertSessionHasNoErrors();
        $this->assertSame('Lost', $q->enquiry->fresh()->status->value);
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Accepted'])->assertSessionHasNoErrors();
        $this->assertSame('Open', $q->enquiry->fresh()->status->value);
        $this->assertNull($q->fresh()->status_reason);

        // a new version: Revise copies items and costing into a new Draft
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/revise")->assertSessionHasNoErrors();
        $rev1 = Quotation::where('is_latest', true)->first();
        $this->assertSame(1, $rev1->revision_no);
        $this->assertSame('Draft', $rev1->status->value);
        $this->assertSame($q->cost->total_cost, $rev1->cost->total_cost);
        $this->assertSame($q->grand_total, $rev1->grand_total);
    }

    // ---- PO / Agreement received ----

    public function test_po_against_an_accepted_quotation_closes_the_lead(): void
    {
        $admin = $this->admin();
        $q = $this->saveQuotation();
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/sent");
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Accepted']);
        $this->assertSame(1, Quotation::poPending()->count());
        $this->actingAs($admin)->get('/crm/quotations/list?view=po-pending')->assertOk()->assertSee($q->quotation_no);

        $this->actingAs($admin)->get("/crm/pos/create?quotation={$q->id}")->assertOk()->assertSee('455');
        $this->actingAs($admin)->post('/crm/pos', [])->assertSessionHasErrors(['quotation_id', 'po_no', 'po_date', 'po_value', 'received_by', 'received_date']);
        $this->actingAs($admin)->post('/crm/pos', [
            'quotation_id' => $q->id, 'po_no' => 'KP/PO/77', 'po_date' => '10/10/2026', 'po_value' => '455', 'received_by' => $this->crmEmployee()->id,
            'received_date' => '10/10/2026', 'valid_from' => '01/11/2026', 'valid_to' => '01/10/2026',
        ])->assertSessionHasErrors('valid_to');
        $this->actingAs($admin)->post('/crm/pos', [
            'quotation_id' => $q->id, 'po_no' => 'KP/PO/77', 'po_date' => '10/10/2026', 'po_value' => '455', 'po_quantity' => '10', 'received_by' => $this->crmEmployee()->id,
            'received_date' => '10/10/2026', 'valid_from' => '01/11/2026', 'valid_to' => '31/10/2027', 'payment_terms' => '50% advance', 'contact_name' => 'Kavita Rao',
        ])->assertRedirect();
        $po = CustomerPo::firstOrFail();
        $this->assertSame($q->enquiry_id, $po->enquiry_id);
        $this->assertSame($q->prospect_id, $po->prospect_id);
        $this->assertSame('PO received', $q->enquiry->fresh()->status->value);
        $this->assertSame(0, Quotation::poPending()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'customer_po.created', 'subject_id' => $po->id]);

        // a quotation with a PO cannot be deleted; deleting the PO opens the lead again
        $this->actingAs($admin)->delete("/crm/quotations/{$q->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->delete("/crm/pos/{$po->id}")->assertRedirect('/crm/pos');
        $this->assertSame('Open', $q->enquiry->fresh()->status->value);

        $this->actingAs($this->makeUser('Purchase', 'buyer@company.example', '9822000020'))->get('/crm/pos')->assertForbidden();
    }

    // ---- Dashboards and reports ----

    public function test_dashboard_shows_the_crm_figures_and_charts(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry(['enquiry_date' => now()->format('d/m/Y'), 'next_action_date' => now()->subDay()->format('d/m/Y'), 'expected_closing_date' => now()->endOfMonth()->format('d/m/Y')]);
        $this->crmEnquiry(['enquiry_date' => now()->format('d/m/Y'), 'lead_rating' => 'Cold', 'lead_source_id' => LeadSource::where('name', 'BNI')->value('id'),
            'next_action_date' => now()->addDays(4)->format('d/m/Y')]);

        $res = $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Enquiry vs PO received')->assertSee('Total open leads')->assertSee('Hot leads')->assertSee('Activities due / overdue')
            ->assertSee('Verbal wins / PO pending')->assertSee('Expected closing this month')->assertSee('Enquiry source performance')
            ->assertSee('Enquiries by category')->assertSee('Lead ageing')->assertSee($e->enquiry_no);
        $d = $res->viewData('d');
        $this->assertSame(2, $d['kpis']['enquiries']);
        $this->assertSame(2, $d['kpis']['open']);
        $this->assertSame(1, $d['kpis']['hot']);
        $this->assertSame(1, $res->viewData('due')['count']);
        $this->assertSame(1, $d['kpis']['closing']);
        $this->assertEqualsCanonicalizing(['Website', 'BNI'], array_column($d['sources']['slices'], 'label'));
        $this->assertSame(2, $d['ageing']['0 to 7 days']);
        $this->assertSame(2, array_sum(array_column($d['monthlyTable'], 'enquiries')));

        // the old sample address opens the real dashboard; a user without CRM sees no CRM figures
        $this->actingAs($admin)->get('/app/dashboard')->assertRedirect('/dashboard');
        $buyer = $this->makeUser('Purchase', 'buyer@company.example', '9822000020');
        $this->actingAs($buyer)->get('/dashboard')->assertOk()->assertDontSee('Total open leads');
    }

    public function test_lead_ageing_and_lead_report_download(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry(['enquiry_date' => now()->subDays(20)->format('d/m/Y')]);
        $this->actingAs($admin)->post("/crm/enquiries/{$e->id}/activities", ['activity_type' => 'Call', 'activity_date' => now()->format('d/m/Y'), 'details' => 'Called']);
        $q = $this->saveQuotation(['enquiry_id' => $e->id]);
        $year = FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];

        $this->actingAs($admin)->get('/reports/crm/lead-ageing')->assertOk()->assertSee('Download options');
        $csv = $this->actingAs($admin)->get('/reports/crm/lead-ageing/download?'.http_build_query($range + ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString($e->enquiry_no, $csv);
        $this->assertStringContainsString('16 to 30 days', $csv);

        $csv = $this->actingAs($admin)->get('/reports/crm/lead-report/download?'.http_build_query($range + ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString($q->quotation_no, $csv);
        $this->assertStringContainsString('Draft', $csv);
        $pdf = $this->actingAs($admin)->get('/reports/crm/lead-report/download?'.http_build_query($range + ['format' => 'pdf']));
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->actingAs($admin)->get('/reports/crm/lead-report/download?'.http_build_query($range + ['format' => 'csv', 'rating' => 'Lukewarm']))->assertSessionHasErrors('rating');
    }

    // ---- Permissions, menu and other units ----

    public function test_new_pages_need_permission_and_stay_in_the_users_units(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry();
        $buyer = $this->makeUser('Purchase', 'buyer@company.example', '9822000020');
        foreach (['/crm/enquiries', '/crm/enquiries/list', '/crm/site-visits', '/crm/pos', "/crm/enquiries/{$e->id}"] as $url) {
            $this->actingAs($buyer)->get($url)->assertForbidden();
        }
        $this->actingAs($buyer)->post('/crm/enquiries', $this->enquiryPayload())->assertForbidden();

        // Sales has the new CRM pages; the menu lists them in workflow order
        $sales = $this->sales();
        $html = $this->actingAs($sales)->get('/crm/enquiries')->assertOk()->getContent();
        // (Sat 18/10/2026: numbered like the enquiry's progress bar)
        $this->assertLessThan(strpos($html, '>3 '), strpos($html, '>2 Site visit<'));
        $this->assertLessThan(strpos($html, '>2 Site visit<'), strpos($html, '>1 Enquiry / Lead<'));

        // another unit's enquiry is not reachable
        $other = $this->makeUser('Sales', 'other@company.example', '9822000021', [Unit::orderByDesc('id')->first()->id]);
        $this->actingAs($other)->get("/crm/enquiries/{$e->id}")->assertForbidden();
        $this->actingAs($other)->post("/crm/enquiries/{$e->id}/activities", ['activity_type' => 'Call', 'activity_date' => '06/10/2026', 'details' => 'x'])->assertForbidden();
        $this->actingAs($other)->post('/crm/site-visits', ['enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'status' => 'Planned'])->assertSessionHasErrors('enquiry_id');
        $this->actingAs($other)->post('/crm/quotations', $this->quotationPayload(['enquiry_id' => $e->id]))->assertSessionHasErrors('enquiry_id');
        $this->actingAs($other)->getJson('/crm/enquiries/lookup?q=Kaveri')->assertOk()->assertExactJson([]);
    }

    public function test_lists_page_on_the_server(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 30; $i++) {
            $this->crmEnquiry();
        }
        $res = $this->actingAs($admin)->get('/crm/enquiries/list')->assertOk();
        $this->assertSame(25, $res->viewData('rows')->count());
        $this->assertSame(30, $res->viewData('rows')->total());
        $this->actingAs($admin)->get('/crm/enquiries/list?page=2')->assertOk()->assertViewHas('rows', fn ($p) => $p->count() === 5);
        $this->actingAs($admin)->get('/crm/enquiries/list?per_page=50&view=hot&sort=next&dir=asc')->assertOk()
            ->assertViewHas('rows', fn ($p) => $p->count() === 30 && str_contains($p->url(2), 'view=hot'));
        $this->assertCount(20, $this->actingAs($admin)->getJson('/crm/enquiries/lookup')->json());
        $this->actingAs($admin)->get('/admin/masters/cities?per_page=25')->assertOk()->assertViewHas('rows', fn ($p) => $p->total() === 17);
    }
}
