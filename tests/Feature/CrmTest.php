<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FinancialYear;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CrmTest extends TestCase
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

    private function prospectPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Orion Agro Equipment',
            'market' => 'Domestic',
            'contact_person' => 'Sanjay Patil',
            'position' => 'Purchase manager',
            'phone_1' => '+91 98220 11111',
            'email' => 'Sanjay@Orion.example',
            'gstin' => '27aaaco1234f1z5',
            'industry_type' => 'Agriculture machinery',
            'address' => 'MIDC Chakan, Pune',
        ], $over);
    }

    private function quotationPayload(array $over = []): array
    {
        return $this->newQuotationPayload($over);
    }

    private function fy(): string
    {
        return FinancialYear::forDate(now())->code;
    }

    public function test_prospect_add_update_delete_are_logged(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/prospects', $this->prospectPayload())->assertRedirect();
        $p = Prospect::where('name', 'Orion Agro Equipment')->first();
        $this->assertSame('sanjay@orion.example', $p->email);
        $this->assertSame('27AAACO1234F1Z5', $p->gstin);
        $this->assertSame(Unit::first()->id, $p->unit_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'prospect.created', 'subject_type' => 'prospect', 'subject_id' => $p->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get('/crm/prospects')->assertOk()->assertSee('Orion Agro Equipment');

        $this->actingAs($admin)->put("/crm/prospects/{$p->id}", $this->prospectPayload(['market' => 'Export', 'phone_2' => '02012345678']))->assertRedirect("/crm/prospects/{$p->id}");
        $log = ActivityLog::where('action', 'prospect.updated')->latest('id')->first();
        $this->assertSame(['old' => 'Domestic', 'new' => 'Export'], $log->changes['Domestic / Export']);

        $this->actingAs($admin)->delete("/crm/prospects/{$p->id}")->assertRedirect('/crm/prospects');
        $this->assertSoftDeleted('prospects', ['id' => $p->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'prospect.deleted', 'subject_id' => $p->id]);
    }

    public function test_prospect_required_fields(): void
    {
        $this->actingAs($this->admin())->post('/crm/prospects', [])->assertSessionHasErrors(['name', 'market', 'phone_1']);
        $this->actingAs($this->admin())->post('/crm/prospects', $this->prospectPayload(['gstin' => 'BAD', 'phone_1' => 'abc']))->assertSessionHasErrors(['gstin', 'phone_1']);
    }

    public function test_quick_prospect_from_quotation_page_returns_json(): void
    {
        $this->actingAs($this->admin())->postJson('/crm/prospects/quick', $this->prospectPayload(['name' => 'Apex Auto']))
            ->assertCreated()->assertJsonPath('name', 'Apex Auto');
        $this->actingAs($this->admin())->postJson('/crm/prospects/quick', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone_1']);
    }

    public function test_quotation_gets_numbers_lines_and_log(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload())->assertRedirect();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload(['site' => 'Second']));

        $q = Quotation::orderBy('id')->first();
        $fy = $this->fy();
        $this->assertSame("ENQ/$fy/0001", $q->enquiry_no);
        $this->assertSame("QT/$fy/0001", $q->quotation_no);
        $this->assertSame(0, $q->revision_no);
        $this->assertCount(1, $q->lines);
        $this->assertSame('45.5000', $q->lines[0]->rate);
        $this->assertSame('455.00', $q->lines[0]->amount);
        $this->assertSame('455.00', $q->grand_total);
        $this->assertSame('2026-10-05', $q->enquiry_receipt_date->toDateString());
        $this->assertSame(\App\Models\Enquiry::first()->id, $q->enquiry_id);
        $this->assertSame('Draft', $q->status->value);
        $this->assertSame($admin->id, $q->prepared_by);
        $this->assertSame("QT/$fy/0002", Quotation::orderByDesc('id')->first()->quotation_no);
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.created', 'subject_type' => 'quotation', 'subject_id' => $q->id]);

        $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk()->assertSee("QT/$fy/0001")->assertSee('Front bracket');
        $pdf = $this->actingAs($admin)->get("/crm/quotations/{$q->id}/pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_quotation_required_fields(): void
    {
        $this->actingAs($this->admin())->post('/crm/quotations', ['lines' => []])
            ->assertSessionHasErrors(['enquiry_id', 'sales_person_id', 'quotation_date', 'currency_id', 'lines']);

        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload([
            'sales_person_id' => $admin->id,
            'lines' => [['item_no' => 'X1', 'description' => '', 'rate' => 'abc']],
        ]))->assertSessionHasErrors(['sales_person_id', 'lines.0.description', 'lines.0.rate', 'lines.0.qty']);
        $this->assertSame(0, Quotation::count());
    }

    public function test_revise_keeps_earlier_revision_read_only(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $rev0 = Quotation::first();

        $this->actingAs($admin)->post("/crm/quotations/{$rev0->id}/revise")->assertRedirect();
        $rev1 = Quotation::where('revision_no', 1)->first();
        $this->assertSame($rev0->quotation_no, $rev1->quotation_no);
        $this->assertSame($rev0->id, $rev1->previous_revision_id);
        $this->assertTrue($rev1->is_latest);
        $this->assertFalse($rev0->fresh()->is_latest);
        $this->assertCount(1, $rev1->lines);
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.revised', 'subject_id' => $rev1->id]);

        // the earlier revision cannot be changed or revised again
        $this->actingAs($admin)->put("/crm/quotations/{$rev0->id}", $this->quotationPayload(['site' => 'Changed']))->assertSessionHasErrors('delete');
        $this->actingAs($admin)->post("/crm/quotations/{$rev0->id}/revise")->assertSessionHasErrors('delete');
        $this->assertSame('Tower B lobby', $rev0->fresh()->site);

        // the current one can, and the change is logged
        $this->actingAs($admin)->put("/crm/quotations/{$rev1->id}", $this->quotationPayload(['payment_terms' => '30 days credit']))->assertRedirect("/crm/quotations/{$rev1->id}");
        $log = ActivityLog::where('action', 'quotation.updated')->latest('id')->first();
        $this->assertSame(['old' => '50% advance', 'new' => '30 days credit'], $log->changes['Payment terms']);

        // a prospect on a quotation cannot be deleted
        $this->actingAs($admin)->delete('/crm/prospects/'.$rev1->prospect_id)->assertSessionHasErrors('delete');

        $this->actingAs($admin)->delete("/crm/quotations/{$rev1->id}")->assertRedirect('/crm/quotations');
        $this->assertSoftDeleted('quotations', ['id' => $rev0->id]);
        $this->assertSoftDeleted('quotations', ['id' => $rev1->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.deleted']);
    }

    public function test_quotation_can_use_a_customer_from_the_party_master(): void
    {
        $party = Party::create(['name' => 'Apex Auto Components', 'type' => 'Customer', 'status' => 'Active']);
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['party_id' => $party->id, 'prospect_id' => '', 'prospect_type' => 'Existing Client']))->assertSessionHasNoErrors();
        $enquiry = \App\Models\Enquiry::first();
        $this->assertSame($party->id, $enquiry->party_id);
        $this->actingAs($this->admin())->post('/crm/quotations', $this->quotationPayload(['enquiry_id' => $enquiry->id]))->assertRedirect();
        $this->assertSame($party->id, Quotation::first()->party_id);
        $this->assertNull(Quotation::first()->prospect_id);
    }

    public function test_crm_pages_and_reports_need_permission(): void
    {
        $buyer = $this->makeUser('Purchase', 'buyer@company.example', '9822000020');
        $this->actingAs($buyer)->get('/crm/quotations')->assertForbidden();
        $this->actingAs($buyer)->get('/crm/prospects')->assertForbidden();
        $this->actingAs($buyer)->post('/crm/prospects', $this->prospectPayload())->assertForbidden();
        $this->actingAs($buyer)->get('/reports/crm/prospects')->assertForbidden();

        // Sales may work on quotations; the old sample-screen URL opens the real page
        $sales = $this->sales();
        $this->actingAs($sales)->get('/crm/quotations')->assertOk();
        $this->actingAs($sales)->get('/app/quotation')->assertRedirect('/crm/quotations');
        $this->actingAs($sales)->get('/reports/crm/enquiries')->assertOk()->assertSee('Download options');
    }

    public function test_reports_download_csv_and_pdf(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/prospects', $this->prospectPayload(['market' => 'Export']));
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $year = FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];

        $csv = $this->actingAs($admin)->get('/reports/crm/prospects/download?'.http_build_query($range + ['format' => 'csv', 'market' => 'Export']));
        $csv->assertOk();
        $this->assertStringContainsString('Orion Agro Equipment', $csv->streamedContent());

        $none = $this->actingAs($admin)->get('/reports/crm/prospects/download?'.http_build_query($range + ['format' => 'csv', 'market' => 'Domestic']));
        $this->assertStringNotContainsString('Orion Agro Equipment', $none->streamedContent());

        $enq = $this->actingAs($admin)->get('/reports/crm/enquiries/download?'.http_build_query(['from' => '01/10/2026', 'to' => '31/10/2026', 'format' => 'csv', 'status' => 'Open', 'rating' => 'Hot']));
        $content = $enq->streamedContent();
        $this->assertStringContainsString(\App\Models\Enquiry::first()->enquiry_no, $content);
        // the enquiry's contact comes from its Prospect, Orion (22/10/2026)
        $this->assertStringContainsString('Sanjay Patil', $content);
        $cold = $this->actingAs($admin)->get('/reports/crm/enquiries/download?'.http_build_query(['from' => '01/10/2026', 'to' => '31/10/2026', 'format' => 'csv', 'rating' => 'Cold']));
        $this->assertStringNotContainsString('Sanjay Patil', $cold->streamedContent());

        $pdf = $this->actingAs($admin)->get('/reports/crm/enquiries/download?'.http_build_query($range + ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $this->actingAs($admin)->get('/reports/crm/enquiries/download?from=31/12/2026&to=01/01/2026&format=csv')->assertSessionHasErrors('to');
    }

    public function test_import_prospects_and_quotations_from_csv(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();
        $csv = "Prospect name,Domestic / Export,Contact no 1,Email\nKaveri Precision,domestic,9822012345,a@b.example\nShree Castings,Export,+44 20 7946 0000,\n";
        $this->actingAs($admin)->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertRedirect('/crm/prospects');
        $this->assertSame(2, Prospect::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'prospect.imported']);

        // a bad row stops the whole file
        $bad = "Prospect name,Domestic / Export,Contact no 1\nGood one,Domestic,9822012346\n,Domestic,\n";
        $this->actingAs($admin)->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $bad)])->assertSessionHasErrors('file');
        $this->assertSame(2, Prospect::count());

        $e1 = $this->crmEnquiry(['prospect_id' => Prospect::where('name', 'Kaveri Precision')->value('id')]);
        $e2 = $this->crmEnquiry(['prospect_id' => Prospect::where('name', 'Shree Castings')->value('id')]);
        $q = "Group,Enquiry no,Quotation date,Sales person login email,Currency,Item no,Item description,Qty,Unit,Rate,Discount %,Tax %\n"
            ."A,{$e1->enquiry_no},01/10/2026,{$sales->email},INR,K-1,Gear,2,Nos,10,,18\n"
            ."A,{$e1->enquiry_no},01/10/2026,{$sales->email},INR,,Shaft,1,Nos,5,,\n"
            ."B,{$e2->enquiry_no},02/10/2026,{$sales->email},USD,,Housing,1,Set,99.5,10,\n";
        $this->actingAs($admin)->post('/crm/quotations/import', ['file' => UploadedFile::fake()->createWithContent('q.csv', $q)])->assertRedirect('/crm/quotations');
        $this->assertSame(2, Quotation::count());
        $first = Quotation::orderBy('id')->first();
        $this->assertCount(2, $first->lines);
        $this->assertSame('Kaveri Precision', $first->prospect->name);
        $this->assertSame('28.60', $first->grand_total); // 2 x 10 + 18% = 23.60, plus 5
        $this->assertSame('89.55', Quotation::orderByDesc('id')->first()->grand_total);

        // an unknown enquiry stops the whole file
        $bad = "Group,Enquiry no,Quotation date,Sales person login email,Item description,Qty,Rate\nA,ENQ/00-00/9999,01/10/2026,{$sales->email},Gear,1,1\n";
        $this->actingAs($admin)->post('/crm/quotations/import', ['file' => UploadedFile::fake()->createWithContent('q.csv', $bad)])->assertSessionHasErrors('file');
        $this->assertSame(2, Quotation::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'quotation.imported']);

        $export = $this->actingAs($admin)->get('/crm/quotations/export');
        $this->assertStringContainsString('Housing', $export->streamedContent());
    }
}
