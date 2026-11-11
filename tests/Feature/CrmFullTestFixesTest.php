<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFormIsComplete;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\LeadSource;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fixes from the full CRM test with dummy data (14/10/2026): B1 to B5. */
class CrmFullTestFixesTest extends TestCase
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

    private function lines(int $n): array
    {
        return array_map(fn ($i) => ['item_no' => 'P-'.$i, 'description' => 'Plant '.$i, 'qty' => '1', 'uom' => 'Nos', 'rate' => '100', 'discount_percent' => '', 'tax_structure_id' => ''], range(1, $n));
    }

    // ---- B1: a 200-item quotation saves; a form PHP may have cut off is never saved half-read ----

    public function test_b1_items_sent_as_one_field_save_200_items(): void
    {
        $payload = $this->newQuotationPayload();
        unset($payload['lines']);
        $payload['lines_json'] = json_encode($this->lines(200));
        $this->assertLessThan(100, count($payload, COUNT_RECURSIVE)); // far below max_input_vars

        $this->actingAs($this->sales())->post('/crm/quotations', $payload)->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->assertCount(200, $q->lines);
        $this->assertSame('Plant 200', $q->lines->last()->description);
        $this->assertSame('20000.00', $q->grand_total);

        // 201 items: the item limit, with a clear message
        $payload['lines_json'] = json_encode($this->lines(201));
        $this->actingAs($this->sales())->post('/crm/quotations', $payload)->assertSessionHasErrors('lines');
        $this->assertSame(1, Quotation::count());
    }

    public function test_b1_a_form_at_the_max_input_vars_limit_is_refused_and_nothing_is_saved(): void
    {
        $this->assertGreaterThan(0, EnsureFormIsComplete::limit());
        config(['nursery.max_input_vars' => 1000]);
        // 150 items sent as separate fields, as without JavaScript: 150 × 7 = 1,050 fields, more than PHP reads
        $payload = $this->newQuotationPayload(['lines' => $this->lines(150)]);
        $this->actingAs($this->sales())->from('/crm/quotations/create')->post('/crm/quotations', $payload)
            ->assertRedirect('/crm/quotations/create')->assertSessionHasErrors('form');
        $this->assertSame(0, Quotation::count());
        $this->actingAs($this->sales())->get('/crm/quotations/create')->assertSee('Nothing was saved')->assertSee('too many fields');

        // 100 items as separate fields still save; the quotation page itself sends its items as one field
        $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload(['lines' => $this->lines(100)]))->assertSessionHasNoErrors();
        $this->assertCount(100, Quotation::firstOrFail()->lines);
        $this->assertStringContainsString("name='lines_json'", file_get_contents(public_path('js/crm.js')));
    }

    // ---- B2: no PO against a Rejected or Expired quotation; a PO makes the quotation Accepted ----

    public function test_b2_po_refused_for_rejected_and_expired_and_marks_accepted(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales)->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/submit");
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/sent");
        $po = fn () => ['quotation_id' => $q->id, 'po_no' => 'KP/PO/1', 'po_date' => '10/10/2026', 'po_value' => '455', 'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026'];

        foreach (['Rejected', 'Expired'] as $status) {
            $this->actingAs($sales)->post("/crm/quotations/{$q->id}/customer-status", ['status' => $status, 'status_reason' => 'Price too high'])->assertSessionHasNoErrors();
            $this->actingAs($sales)->post('/crm/pos', $po())->assertSessionHasErrors('quotation_id');
            $this->actingAs($sales)->get('/crm/pos/create'); // shows the error once (the typed choice is kept)
            $this->actingAs($sales)->get('/crm/pos/create')->assertViewHas('quotationOptions', fn ($o) => ! $o->has($q->id));
        }
        $this->assertSame(0, CustomerPo::count());
        $this->assertSame('Lost', $q->enquiry->fresh()->status->value);

        // Negotiation, then a PO: the quotation becomes Accepted and the lead PO received
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/customer-status", ['status' => 'Negotiation']);
        $this->actingAs($sales)->post('/crm/pos', $po())->assertSessionHasNoErrors();
        $this->assertSame('Accepted', $q->fresh()->status->value);
        $this->assertSame('PO received', $q->enquiry->fresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.status_changed', 'subject_id' => $q->id, 'description' => $q->activityLabel().' is now Accepted (PO KP/PO/1 received)']);
    }

    // ---- B3: a mobile number typed the usual way finds the lead ----

    public function test_b3_enquiry_picker_finds_a_mobile_typed_any_way(): void
    {
        // the enquiry takes its contact no from the Prospect (22/10/2026)
        $this->prospectFixture()->update(['phone_1' => '+91 98220 00005']);
        $e = $this->crmEnquiry();
        $this->assertSame('9822000005', $e->fresh()->contact_no_digits);
        foreach (['9822000005', '98220', '+91 98220 00005', '+919822000005', '098220 00005'] as $typed) {
            $this->actingAs($this->admin())->getJson('/crm/enquiries/lookup?q='.urlencode($typed))->assertOk()->assertJsonPath('0.id', $e->id);
        }
        $this->actingAs($this->admin())->getJson('/crm/enquiries/lookup?q=9999999')->assertExactJson([]);
        $this->assertSame('9822000005', Enquiry::phoneDigits('98220-00005'));
    }

    // ---- B4: a file's content must match its .pdf / image name ----

    public function test_b4_a_text_file_renamed_to_pdf_is_refused(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $path = tempnam(sys_get_temp_dir(), 'b4');
        file_put_contents($path, "This is only text, not a PDF.\n");
        $fake = new UploadedFile($path, 'fake.pdf', null, null, true);
        $this->actingAs($this->admin())->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => $fake])
            ->assertSessionHasErrors(['file' => 'This file is not a real PDF file (its content is text/plain). Save it as PDF and upload it again.']);
        $this->assertSame(0, Document::count());

        // a real PDF and a .txt file are still accepted
        $pdf = tempnam(sys_get_temp_dir(), 'b4');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        $this->actingAs($this->admin())->post("/crm/documents/quotation/{$q->id}", ['category' => 'BOQ', 'file' => new UploadedFile($pdf, 'boq.pdf', null, null, true)])->assertSessionHasNoErrors();
        $txt = tempnam(sys_get_temp_dir(), 'b4');
        file_put_contents($txt, "notes\n");
        $this->actingAs($this->admin())->post("/crm/documents/quotation/{$q->id}", ['category' => 'Other', 'file' => new UploadedFile($txt, 'notes.txt', null, null, true)])->assertSessionHasNoErrors();
        $this->assertSame(2, Document::count());
    }

    // ---- End-to-end test M1: a deleted employee frees its login ----

    public function test_m1_deleting_an_employee_frees_its_login_for_another_employee(): void
    {
        $admin = $this->admin();
        $login = $this->sales();
        $this->actingAs($admin)->post('/admin/masters/employees', ['code' => 'E004', 'name' => 'Old Hand', 'user_id' => $login->id, 'status' => 'Active'])->assertSessionHasNoErrors();
        $old = \App\Models\Employee::where('code', 'E004')->firstOrFail();

        $this->actingAs($admin)->delete("/admin/masters/employees/{$old->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('employees', ['id' => $old->id, 'user_id' => null]);
        $log = \App\Models\ActivityLog::where('action', 'employee.deleted')->where('subject_id', $old->id)->firstOrFail();
        $this->assertSame($login->name, $log->changes['fields']['Login user']); // the log keeps which login it was

        $this->actingAs($admin)->post('/admin/masters/employees', ['code' => 'E005', 'name' => 'New Hand', 'user_id' => $login->id, 'status' => 'Active'])->assertSessionHasNoErrors();
        $new = \App\Models\Employee::where('code', 'E005')->firstOrFail();
        $this->assertSame($login->id, $new->user_id);

        // the login's own follow-ups follow the new employee
        $mine = $this->crmEnquiry(['assigned_to' => $new->id, 'next_action_date' => now()->subDay()->format('d/m/Y')]);
        $this->crmEnquiry(['next_action_date' => now()->subDay()->format('d/m/Y')]);
        $due = app(\App\Services\CrmAlerts::class)->due($login, Unit::first());
        $this->assertSame([$mine->enquiry_no], array_column($due['list'], 'no'));
    }

    // ---- B5: inactive masters and closed enquiries in a hand-made post ----

    public function test_b5_inactive_master_and_closed_enquiry_are_refused(): void
    {
        $admin = $this->admin();
        $src = LeadSource::where('name', 'BNI')->firstOrFail();
        $e = $this->crmEnquiry(['lead_source_id' => $src->id]);
        $src->update(['is_active' => false]);

        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['lead_source_id' => $src->id]))->assertSessionHasErrors('lead_source_id');
        // the enquiry that already has it can still be saved
        $this->actingAs($admin)->put("/crm/enquiries/{$e->id}", $this->enquiryPayload(['lead_source_id' => $src->id, 'lead_rating' => 'Warm']))->assertSessionHasNoErrors();
        $this->assertSame('Warm', $e->fresh()->lead_rating->value);

        // no new quotation on a Lost enquiry
        Enquiry::whereKey($e->id)->update(['status' => 'Lost']);
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['enquiry_id' => $e->id]))->assertSessionHasErrors('enquiry_id');
        $this->assertSame(0, Quotation::count());
    }
}
