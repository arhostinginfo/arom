<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModuleSettings;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * (26/11/2026) Quotation: "Contact" is now "Contact Person" and "Site" is "Site Name" (form, Page setup, PDF, CSV,
 * import template, history); an import file with the earlier headings still works. A "BOQ document" upload beside
 * Notes saves the file as a BOQ document of the quotation on Submit, while the BOQ part is on.
 */
class QuotationLabelsAndBoqTest extends TestCase
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
        $u = User::where('email', 'priya@company.example')->first();
        if (! $u) {
            $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
            $u->assignRole('Sales');
            $u->units()->sync([Unit::first()->id]);
        }

        return $u;
    }

    private function setBoq(bool $on): void
    {
        $all = ['crm' => true, 'site_visit' => true, 'boq' => $on, 'costing' => true];
        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => array_map(fn ($v) => $v ? '1' : '0', $all)])->assertSessionHasNoErrors();
        app()->forgetInstance(ModuleSettings::class);
        Cache::flush();
    }

    public function test_contact_person_and_site_name_everywhere(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get('/crm/quotations/create')->assertOk()->getContent();
        $this->assertStringContainsString('<label for="contact_name">Contact Person', $html);
        $this->assertStringContainsString('<label for="site">Site Name', $html);
        $this->assertStringNotContainsString('<label for="contact_name">Contact<', $html);

        $this->assertSame('Contact Person', config('nursery.page_fields.quotation.fields.contact_name.label'));
        $this->assertSame('Site Name', config('nursery.page_fields.quotation.fields.site.label'));
        $this->assertContains('Contact Person', QuotationService::IMPORT_COLUMNS);
        $this->assertContains('Site Name', QuotationService::IMPORT_COLUMNS);

        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['contact_name' => 'Kavita Rao', 'site' => 'Tower B lobby']))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $log = \App\Models\ActivityLog::where('action', 'quotation.created')->latest('id')->first();
        $this->assertSame('Kavita Rao', $log->changes['fields']['Contact Person']);
        $this->assertSame('Tower B lobby', $log->changes['fields']['Site Name']);

        $csv = $this->actingAs($admin)->get('/crm/quotations/export')->assertOk()->streamedContent();
        $head = str_getcsv(strtok(preg_replace('/^\xEF\xBB\xBF/', '', $csv), "\n"));
        $this->assertContains('Contact Person', $head);
        $this->assertContains('Site Name', $head);
        $template = $this->actingAs($admin)->get('/crm/quotations/import-template')->assertOk()->streamedContent();
        $this->assertStringContainsString('Contact Person', $template);
        $this->assertStringContainsString('Site Name', $template);

        $pdf = view('pdf.quotation', ['q' => $q->fresh(['lines', 'currency', 'unit', 'salesPerson', 'preparer', 'prospect'])])->render();
        $this->assertStringContainsString('<td class="k">Contact Person</td>', $pdf);
        $this->assertStringContainsString('<td class="k">Site Name</td>', $pdf);
    }

    public function test_import_with_new_and_old_headings(): void
    {
        $sales = $this->sales();
        $enq = $this->crmEnquiry();
        foreach ([['Contact Person', 'Site Name', 'Anjali', 'Gate 1'], ['Contact', 'Site', 'Ravi', 'Gate 2']] as [$c, $s, $who, $where]) {
            $head = ['Group', 'Enquiry no', 'Quotation date', $c, $s, 'Sales person login email', 'Currency', 'Item description', 'Qty', 'Unit', 'Rate', 'Tax'];
            $row = ['1', $enq->enquiry_no, '05/10/2026', $who, $where, $sales->email, 'INR', 'Areca palm', '2', 'NOS', '100', 'GST 18%'];
            $file = UploadedFile::fake()->createWithContent('q.csv', implode(',', $head)."\n".implode(',', $row)."\n");
            $this->actingAs($sales)->post('/crm/quotations/import', ['file' => $file])->assertSessionHasNoErrors();
            $q = Quotation::latest('id')->firstOrFail();
            $this->assertSame([$who, $where], [$q->contact_name, $q->site]);
        }
    }

    public function test_boq_document_beside_notes_is_saved_on_submit(): void
    {
        Storage::fake(\App\Services\DocumentService::diskName());
        $admin = $this->admin();
        $this->setBoq(true);

        $html = $this->actingAs($admin)->get('/crm/quotations/create')->assertOk()->getContent();
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertMatchesRegularExpression('#<label for="notes">Notes.*?<label for="boq_file">BOQ document</label>#s', $html);

        // new quotation with a BOQ file
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('boq-tower-b.pdf', 200, 'application/pdf')]))
            ->assertSessionHasNoErrors()->assertSessionHas('toast', fn ($t) => str_contains($t, 'with its BOQ document'));
        $q = Quotation::latest('id')->firstOrFail();
        $doc = Document::where('documentable_type', 'quotation')->where('documentable_id', $q->id)->sole();
        $this->assertSame(['BOQ', 'boq-tower-b.pdf', $admin->id], [$doc->category, $doc->original_name, $doc->uploaded_by]);
        Storage::disk(\App\Services\DocumentService::diskName())->assertExists($doc->path);
        $this->assertNull($q->getAttribute('boq_file'));

        // edit: a second BOQ file is added; saving without a file keeps the documents
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id, 'boq_file' => UploadedFile::fake()->create('boq-v2.xlsx', 50)]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id]))->assertSessionHasNoErrors();
        $this->assertSame(['boq-tower-b.pdf', 'boq-v2.xlsx'], Document::where('documentable_id', $q->id)->where('documentable_type', 'quotation')->orderBy('id')->pluck('original_name')->all());

        // wrong type / too big: refused, nothing saved
        $count = Quotation::count();
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('run.exe', 10)]))->assertSessionHasErrors('boq_file');
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]))->assertSessionHasErrors('boq_file');
        $this->assertSame($count, Quotation::count());
    }

    public function test_boq_document_content_check_and_error_on_another_field(): void
    {
        Storage::fake(\App\Services\DocumentService::diskName());
        $admin = $this->admin();
        $this->setBoq(true);

        // a text file renamed .pdf is refused, as in "BOQ and attachments"
        $tmp = tempnam(sys_get_temp_dir(), 'boq');
        file_put_contents($tmp, "just some text, not a PDF\n");
        $fake = new UploadedFile($tmp, 'boq.pdf', null, null, true);
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => $fake]))
            ->assertSessionHasErrors(['boq_file' => 'This file is not a real PDF file (its content is text/plain). Save it as PDF and upload it again.']);
        @unlink($tmp);
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('run.exe', 10)]))
            ->assertSessionHasErrors(['boq_file' => 'Upload a PDF, image (JPG, PNG, WEBP), Word, Excel, CSV, text or email file.']);
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]))
            ->assertSessionHasErrors(['boq_file' => 'The BOQ document must not be larger than 10 MB.']);

        // another field has an error: the chosen file is not kept, and the form says to choose it again
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['quotation_date' => 'bad', 'boq_file' => UploadedFile::fake()->create('boq.pdf', 20, 'application/pdf')]))
            ->assertSessionHasErrors(['quotation_date', 'boq_file' => 'Choose the BOQ document again: a chosen file is not kept while the form has an error.']);
        // no file chosen: no BOQ message
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['quotation_date' => 'bad']))
            ->assertSessionHasErrors('quotation_date')->assertSessionDoesntHaveErrors('boq_file');
        $this->assertSame(0, Document::count());
    }

    public function test_boq_upload_not_on_a_quotation_that_cannot_change(): void
    {
        Storage::fake(\App\Services\DocumentService::diskName());
        $admin = $this->admin();
        $this->setBoq(true);
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();

        $this->assertStringContainsString('name="boq_file"', $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk()->getContent());

        // Approved / Sent: no BOQ field, a posted file is not saved
        foreach (['Internal Approval', 'Approved', 'Sent'] as $status) {
            Quotation::whereKey($q->id)->update(['status' => $status]);
            $html = $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk()->getContent();
            $this->assertStringNotContainsString('name="boq_file"', $html, $status);
            $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id, 'boq_file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')]));
            $this->assertSame(0, Document::count(), $status);
        }

        // Revise: the old revision is read-only (no field), the new Draft has it
        $this->actingAs($admin)->post("/crm/quotations/{$q->id}/revise")->assertSessionHasNoErrors();
        $rev = Quotation::where('quotation_no', $q->quotation_no)->where('is_latest', true)->firstOrFail();
        $this->assertNotSame($q->id, $rev->id);
        $this->assertStringNotContainsString('name="boq_file"', $this->actingAs($admin)->get("/crm/quotations/{$q->id}")->assertOk()->getContent());
        $this->assertStringContainsString('name="boq_file"', $this->actingAs($admin)->get("/crm/quotations/{$rev->id}")->assertOk()->getContent());
        // a file posted to the old revision is not saved
        $this->actingAs($admin)->put("/crm/quotations/{$q->id}", $this->newQuotationPayload(['enquiry_id' => $q->enquiry_id, 'boq_file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')]));
        $this->assertSame(0, Document::count());
        $this->actingAs($admin)->put("/crm/quotations/{$rev->id}", $this->newQuotationPayload(['enquiry_id' => $rev->enquiry_id, 'boq_file' => UploadedFile::fake()->create('boq-rev1.pdf', 10, 'application/pdf')]))->assertSessionHasNoErrors();
        $this->assertSame([$rev->id], Document::pluck('documentable_id')->all());
    }

    public function test_customer_po_site_label_is_unchanged(): void
    {
        $this->assertSame('Site', config('nursery.page_fields.customer-po.fields.site.label'));
    }

    public function test_boq_upload_follows_the_boq_switch(): void
    {
        Storage::fake(\App\Services\DocumentService::diskName());
        $admin = $this->admin();
        $this->setBoq(false);

        $html = $this->actingAs($admin)->get('/crm/quotations/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="boq_file"', $html);
        $this->assertStringContainsString('<label for="notes">Notes', $html);
        // a posted file is not read while BOQ is off
        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['boq_file' => UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')]))->assertSessionHasNoErrors();
        $this->assertSame(0, Document::count());
    }
}
