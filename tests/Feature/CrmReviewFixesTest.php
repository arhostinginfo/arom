<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Services\CustomerPoService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fixes from the four reviews of the CRM changes (14/10/2026): architecture (C1-C3), code (M1-M4),
 * UX (M1-M8) and database (DB-C1-DB-C4). Approval steps run as real roles here, not only as Admin.
 */
class CrmReviewFixesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function makeUser(string $role, string $email, string $mobile): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => $mobile, 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function sales(): User
    {
        return User::where('email', 'priya@company.example')->first() ?? $this->makeUser('Sales', 'priya@company.example', '9822000010');
    }

    /** A role that approves quotations but is not Admin. */
    private function approver(): User
    {
        Role::findOrCreate('Approver', 'web')->syncPermissions(['quotation.view', 'quotation-approval.view', 'quotation-approval.edit']);

        return User::where('email', 'anil@company.example')->first() ?? $this->makeUser('Approver', 'anil@company.example', '9822000011');
    }

    private function quotationAs(User $user, array $over = []): Quotation
    {
        $this->actingAs($user)->post('/crm/quotations', $this->newQuotationPayload($over))->assertSessionHasNoErrors();

        return Quotation::latest('id')->first();
    }

    private function line(string $rate): array
    {
        return ['lines' => [['description' => 'Plant rental', 'qty' => '1', 'rate' => $rate]]];
    }

    /** Draft → (approved at once) → Sent → Accepted, as the sales user. */
    private function acceptedQuotation(): Quotation
    {
        $sales = $this->sales();
        $q = $this->quotationAs($sales);
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/sent")->assertSessionHasNoErrors();
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Accepted'])->assertSessionHasNoErrors();

        return $q->fresh();
    }

    private function poPayload(Quotation $q, array $over = []): array
    {
        return array_merge(['quotation_id' => $q->id, 'po_no' => 'KP/PO/77', 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026'], $over);
    }

    // ---- Code review M1: the rupee limit and other currencies; approval by real roles ----

    public function test_m1_approval_limit_boundary_and_other_currencies_with_a_real_approver(): void
    {
        $sales = $this->sales();
        $approver = $this->approver();

        $atLimit = $this->quotationAs($sales, $this->line('100000'));
        $this->actingAs($sales)->post("/crm/quotations/{$atLimit->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Approved', $atLimit->fresh()->status->value);

        $over = $this->quotationAs($sales, $this->line('100000.01'));
        $this->actingAs($sales)->post("/crm/quotations/{$over->id}/submit");
        $this->assertSame('Internal Approval', $over->fresh()->status->value);

        // USD 500 is below the number 1,00,000 but is not rupees: it goes to an approver
        $usd = $this->quotationAs($sales, $this->line('500') + ['currency_id' => Currency::where('code', 'USD')->value('id')]);
        $this->actingAs($sales)->post("/crm/quotations/{$usd->id}/submit")->assertSessionHas('toast', fn ($t) => str_contains($t, 'always need an approver'));
        $this->assertSame('Internal Approval', $usd->fresh()->status->value);

        // the sales user cannot approve or reject; the Approver role can
        $this->actingAs($sales)->post("/crm/quotations/{$usd->id}/approve")->assertForbidden();
        $this->actingAs($sales)->post("/crm/quotations/{$usd->id}/reject", ['remarks' => 'No'])->assertForbidden();
        $this->actingAs($approver)->get('/crm/quotations/list?view=approval')->assertOk()->assertSee($usd->quotation_no);
        $this->actingAs($approver)->get("/crm/quotations/{$usd->id}")->assertOk()->assertSee('Approve');
        $this->actingAs($approver)->post("/crm/quotations/{$usd->id}/approve", ['remarks' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $usd->fresh()->status->value);
        $this->actingAs($approver)->post("/crm/quotations/{$over->id}/reject", ['remarks' => 'Margin too low'])->assertSessionHasNoErrors();
        $this->assertSame('Draft', $over->fresh()->status->value);
    }

    public function test_s1_the_person_who_submitted_cannot_approve_except_admin(): void
    {
        Role::findOrCreate('Sales lead', 'web')->syncPermissions(['quotation.view', 'quotation.add', 'quotation.edit', 'quotation-approval.view', 'quotation-approval.edit']);
        $lead = $this->makeUser('Sales lead', 'lead@company.example', '9822000012');
        $q = app(QuotationService::class)->create($this->newQuotationPayload(['sales_person_id' => $this->sales()->id] + $this->line('250000')), $lead, Unit::first());
        $this->actingAs($lead)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($lead)->post("/crm/quotations/{$q->id}/approve")->assertSessionHasErrors('status');
        $this->assertSame('Internal Approval', $q->fresh()->status->value);
        $this->actingAs($this->approver())->post("/crm/quotations/{$q->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('Approved', $q->fresh()->status->value);
    }

    public function test_approved_and_sent_quotations_refuse_save(): void
    {
        $sales = $this->sales();
        $q = $this->quotationAs($sales);
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($sales)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['site' => 'Changed']))->assertSessionHasErrors('status');
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/sent");
        $this->actingAs($sales)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['site' => 'Changed']))->assertSessionHasErrors('status');
        $this->assertSame('Tower B lobby', $q->fresh()->site);
    }

    // ---- Code review M2 and database review DB-C1: PO only against a sent current quotation; lead status ----

    public function test_m2_po_is_refused_for_draft_pending_and_earlier_revision(): void
    {
        $sales = $this->sales();
        $draft = $this->quotationAs($sales);
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($draft))->assertSessionHasErrors('quotation_id');
        $this->actingAs($sales)->get("/crm/pos/create?quotation={$draft->id}")->assertOk()->assertViewHas('po', fn ($po) => $po->quotation_id === null);

        $pending = $this->quotationAs($sales, $this->line('250000'));
        $this->actingAs($sales)->post("/crm/quotations/{$pending->id}/submit");
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($pending))->assertSessionHasErrors('quotation_id');

        $accepted = $this->acceptedQuotation();
        $this->actingAs($sales)->post("/crm/quotations/{$accepted->id}/revise");
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($accepted->fresh()))->assertSessionHasErrors('quotation_id');
        $this->assertSame(0, CustomerPo::count());
        $this->assertSame('Open', $accepted->enquiry->fresh()->status->value);
    }

    public function test_db_c1_lead_status_follows_the_pos_and_quotations(): void
    {
        $sales = $this->sales();
        $q = $this->acceptedQuotation();
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($q))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($q, ['po_no' => 'KP/PO/77']))->assertSessionHasErrors('po_no');
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($q, ['po_no' => 'KP/AGR/1']))->assertSessionHasNoErrors();
        [$po1, $po2] = CustomerPo::orderBy('id')->get()->all();
        $enquiry = $q->enquiry;

        // a Rejected status no longer overwrites "PO received"
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Rejected', 'status_reason' => 'Changed mind']);
        $this->assertSame('PO received', $enquiry->fresh()->status->value);
        // deleting one of two POs keeps PO received; deleting the last opens the lead (Lost: its quotation is Rejected)
        $this->actingAs($sales)->delete("/crm/pos/{$po1->id}");
        $this->assertSame('PO received', $enquiry->fresh()->status->value);
        $this->actingAs($sales)->delete("/crm/pos/{$po2->id}");
        $this->assertSame('Lost', $enquiry->fresh()->status->value);
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Accepted']);
        $this->assertSame('Open', $enquiry->fresh()->status->value);

        // a PO moved to another enquiry's quotation reopens the first enquiry
        $this->actingAs($sales)->post('/crm/pos', $this->poPayload($q, ['po_no' => 'KP/PO/78']));
        $po = CustomerPo::latest('id')->first();
        $other = $this->crmEnquiry();
        $q2 = $this->quotationAs($sales, ['enquiry_id' => $other->id]);
        foreach (['submit', 'sent'] as $step) {
            $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/$step");
        }
        $this->actingAs($sales)->put("/crm/pos/{$po->id}", $this->poPayload($q2, ['po_no' => 'KP/PO/78']))->assertSessionHasNoErrors();
        $this->assertSame('Open', $enquiry->fresh()->status->value);
        $this->assertSame('PO received', $other->fresh()->status->value);
    }

    // ---- Code review M3, M4 and architecture review C2, C3: documents ----

    public function test_m3_quotation_documents_change_only_on_the_current_draft_and_revise_copies_the_boq(): void
    {
        Storage::fake('local');
        $sales = $this->sales();
        $q = $this->quotationAs($sales);
        $this->actingAs($sales)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $boq = Document::firstOrFail();
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/submit");

        // approved: no upload, no delete, and the box says why
        $this->actingAs($sales)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('other.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($sales)->delete("/crm/documents/{$boq->id}")->assertSessionHasErrors('file');
        $this->assertDatabaseHas('documents', ['id' => $boq->id]);
        $this->actingAs($sales)->get("/crm/quotations/{$q->id}")->assertSee('cannot be changed now')->assertDontSee('id="docFile"', false);

        // Revise: the new Draft gets its own copy of the BOQ; the earlier revision stays locked
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/revise");
        $rev1 = Quotation::where('is_latest', true)->first();
        $copy = Document::where('documentable_id', $rev1->id)->firstOrFail();
        $this->assertSame('boq.pdf', $copy->original_name);
        $this->assertNotSame($boq->path, $copy->path);
        Storage::disk('local')->assertExists($copy->path);
        $this->actingAs($sales)->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($sales)->delete("/crm/documents/{$copy->id}")->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($boq->path);
    }

    public function test_m4_documents_box_pages_on_the_server(): void
    {
        $e = $this->crmEnquiry();
        $now = now();
        DB::table('documents')->insert(array_map(fn ($i) => ['documentable_type' => 'enquiry', 'documentable_id' => $e->id, 'category' => 'Visiting card',
            'original_name' => sprintf('card-%02d.jpg', $i), 'path' => 'documents/enquiry/x'.$i, 'mime' => 'image/jpeg', 'size' => 1000, 'uploaded_by' => $this->admin()->id, 'created_at' => $now], range(1, 12)));

        $page1 = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk();
        $docs = $page1->viewData('docs');
        $this->assertSame(12, $docs['count']);
        $this->assertCount(10, $docs['list']);
        $page1->assertSee('card-12.jpg')->assertDontSee('card-02.jpg')->assertSee('Showing 1 to 10 of 12');
        $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}?dpage=2")->assertOk()->assertSee('card-02.jpg')->assertDontSee('card-12.jpg');
    }

    public function test_c2_documents_go_to_the_configured_disk(): void
    {
        Storage::fake('shared-docs');
        config(['nursery.documents_disk' => 'shared-docs']);
        $e = $this->crmEnquiry();
        $this->actingAs($this->admin())->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Visiting card', 'file' => UploadedFile::fake()->create('card.pdf', 5, 'application/pdf')])->assertSessionHasNoErrors();
        Storage::disk('shared-docs')->assertExists(Document::firstOrFail()->path);
        $this->actingAs($this->admin())->get('/crm/documents/'.Document::first()->id)->assertOk();
    }

    // ---- Architecture review C1, database review DB-C2, UX review M6: alerts and dashboard ----

    public function test_c1_a_saved_follow_up_shows_at_once_and_the_dashboard_matches_the_list(): void
    {
        $sales = $this->sales();
        $mine = $this->crmEmployee(['code' => 'E002', 'name' => 'Priya S', 'user_id' => $sales->id]);
        $e = $this->crmEnquiry(['assigned_to' => $mine->id, 'next_action_date' => now()->subDays(2)->format('d/m/Y')]);
        $this->crmEnquiry(['next_action_date' => now()->subDay()->format('d/m/Y')]); // another employee's lead

        $this->actingAs($sales)->get('/crm/enquiries')->assertSee('id="duePop"', false)->assertSee('1 follow-up(s) are due');
        // the dashboard tile, its list and Enquiry list › Follow-ups due all show the same 1 lead
        $dash = $this->actingAs($sales)->get('/dashboard')->assertOk();
        $this->assertSame(1, $dash->viewData('due')['count']);
        $this->assertSame([$e->enquiry_no], array_column($dash->viewData('due')['list'], 'no'));
        $this->actingAs($sales)->get('/crm/enquiries/list?view=due')->assertViewHas('rows', fn ($p) => $p->total() === 1);

        // a follow-up with a later target clears the alert on the next page, not minutes later
        $this->actingAs($sales)->post("/crm/enquiries/{$e->id}/activities", ['activity_type' => 'Call', 'activity_date' => now()->format('d/m/Y'),
            'details' => 'Spoke', 'followup_action' => 'Visit', 'target_date' => now()->addDays(3)->format('d/m/Y')])->assertSessionHasNoErrors();
        $this->actingAs($sales)->get('/crm/enquiries')->assertDontSee('id="duePop"', false);
        $this->assertSame(0, $this->actingAs($sales)->get('/dashboard')->viewData('due')['count']);

        // no cache is cleared by other saves of the unit any more
        $this->assertFalse(method_exists(\App\Services\CrmAlerts::class, 'bump'));
    }

    // ---- Database review DB-C3: "starts with" searches ----

    public function test_db_c3_pickers_match_the_start_and_numbers_by_their_digits(): void
    {
        $e = $this->crmEnquiry();
        $digits = (string) (int) substr($e->enquiry_no, -4);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q='.$digits)->assertOk()->assertJsonPath('0.id', $e->id);
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q=Kaveri')->assertJsonCount(1);
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q='.urlencode('+91 98220'))->assertJsonCount(1);
        // the middle of a name no longer matches (it cannot use an index)
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q=Precision')->assertExactJson([]);
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q='.urlencode('ENQ/'))->assertJsonCount(1);

        $q = $this->quotationAs($this->sales(), ['enquiry_id' => $e->id]);
        $this->actingAs($admin)->getJson('/crm/quotations/lookup?q='.((int) substr($q->quotation_no, -4)))->assertJsonPath('0.id', $q->id);
        $this->actingAs($admin)->get('/crm/quotations/list?q=Kaveri')->assertSee($q->quotation_no);
        app(\App\Services\SiteVisitService::class)->create(['enquiry_id' => $e->id, 'visit_date' => '08/10/2026', 'status' => 'Planned'], $admin);
        $this->actingAs($admin)->getJson('/crm/site-visits/lookup?q=Kaveri')->assertJsonCount(1);
        $this->actingAs($admin)->getJson('/crm/site-visits/lookup?q=Nobody')->assertExactJson([]);
    }

    public function test_db_c4_index_migration_adds_the_list_and_dashboard_indexes(): void
    {
        $names = fn (string $table) => array_column(\Illuminate\Support\Facades\Schema::getIndexes($table), 'name');
        $this->assertContains('quotations_unit_latest_date_index', $names('quotations'));
        $this->assertContains('site_visits_unit_visit_index', $names('site_visits'));
        $this->assertContains('enquiries_unit_open_date_index', $names('enquiries'));
        $this->assertContains('enquiries_unit_next_index', $names('enquiries'));
        $this->assertContains('customer_pos_enquiry_deleted_index', $names('customer_pos'));
    }

    // ---- Paging the code review asked to cover ----

    public function test_more_lists_page_on_the_server(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry();
        $now = now();
        DB::table('site_visits')->insert(array_map(fn ($i) => ['unit_id' => $e->unit_id, 'enquiry_id' => $e->id, 'visit_at' => $now->copy()->subDays($i), 'status' => 'Done', 'created_at' => $now, 'updated_at' => $now], range(1, 30)));
        DB::table('employees')->insert(array_map(fn ($i) => ['code' => 'B'.$i, 'name' => 'Bulk '.$i, 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now], range(1, 30)));
        for ($i = 0; $i < 26; $i++) {
            app(QuotationService::class)->create($this->newQuotationPayload(['enquiry_id' => $e->id]), $admin, Unit::first());
        }

        $this->actingAs($admin)->get('/crm/site-visits/list')->assertViewHas('rows', fn ($p) => $p->count() === 25 && $p->total() === 30);
        $this->actingAs($admin)->get('/crm/quotations/list')->assertViewHas('rows', fn ($p) => $p->count() === 25 && $p->total() === 26);
        $this->actingAs($admin)->get('/admin/masters/employees')->assertViewHas('rows', fn ($p) => $p->count() === 25 && $p->total() === 31);
        $this->actingAs($admin)->get('/admin/masters/employees?per_page=100')->assertViewHas('rows', fn ($p) => $p->count() === 31);
        $this->actingAs($admin)->get('/crm/site-visits/list?per_page=7')->assertViewHas('rows', fn ($p) => $p->perPage() === 25);
        $this->actingAs($admin)->get('/crm/quotations/list?page=2')->assertViewHas('rows', fn ($p) => $p->count() === 1);
    }

    // ---- UX review M4, M5, M7, M8, M3: what the screens print ----

    public function test_ux_reject_error_due_text_city_picker_items_and_menus(): void
    {
        $sales = $this->sales();
        $approver = $this->approver();
        $q = $this->quotationAs($sales, $this->line('250000'));
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/submit");

        // M4: the reopened Reject dialog shows its error next to the reason
        $this->actingAs($approver)->from("/crm/quotations/{$q->id}")->post("/crm/quotations/{$q->id}/reject", ['remarks' => '']);
        $this->actingAs($approver)->get("/crm/quotations/{$q->id}")->assertSee('id="remarks-err"', false)->assertSee('aria-describedby="remarks-err"', false)->assertSee('data-autoopen', false);

        // M5: due is written out, not only red
        $e = $this->crmEnquiry(['next_action_date' => now()->subDays(3)->format('d/m/Y')]);
        $this->actingAs($this->admin())->get('/crm/enquiries/list')->assertSee('3 day(s) late');
        $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertSee('3 day(s) late');

        // M7: City is a type-ahead; with more than 200 cities the page does not list them all
        $now = now();
        DB::table('cities')->insert(array_map(fn ($i) => ['name' => 'Town '.$i, 'sort_order' => 100 + $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], range(1, 200)));
        $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertSee(route('lookup.cities'), false)->assertDontSee('Town 150');
        $this->actingAs($this->admin())->getJson('/lookup/cities?q=Pun')->assertOk()->assertJsonPath('0.label', 'Pune');
        $this->assertCount(20, $this->actingAs($this->admin())->getJson('/lookup/cities?q=Town')->json());
        $this->actingAs($this->makeUser('Purchase', 'buyer@company.example', '9822000020'))->getJson('/lookup/cities?q=P')->assertForbidden();

        // M8: each item cell carries its label for the phone card layout; M3: bell menu items are menu items
        $this->actingAs($sales)->get("/crm/quotations/{$q->id}")->assertSee('data-label="Qty"', false)->assertSee('data-label="Total" data-line-total', false);
        $this->actingAs($this->admin())->get('/crm/enquiries')->assertSee('class="mi alertmi" role="menuitem"', false);
        $js = file_get_contents(public_path('js/app.js'));
        $this->assertStringContainsString("x.setAttribute('role','menuitem')", $js);  // M3 keyboard menu
        $this->assertStringContainsString('function focusables', $js);              // M2 focus kept in dialogs
        $this->assertStringContainsString('e.target!==dirtyForm', $js);             // M1 unsaved changes guard
    }
}
