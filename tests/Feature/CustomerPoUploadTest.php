<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fri 23/10/2026: PO / Agreement documents chosen in the form of a new PO are saved with it, all or nothing; a saved
 * PO shows its documents box above the form, with Upload for users who can edit POs and no row Delete.
 */
class CustomerPoUploadTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/po-upload-test-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    /** Sales person of the quotation fixture (CrmFixtures::newQuotationPayload). */
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

    private function user(string $role, array $permissions, string $email): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        $u = User::create(['name' => ucfirst($role), 'email' => $email, 'mobile' => '98220'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** A real PDF on disk, so the content check sees application/pdf. */
    private function pdf(string $name): UploadedFile
    {
        file_put_contents($this->dir.'/'.$name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return new UploadedFile($this->dir.'/'.$name, $name, null, null, true);
    }

    /** A quotation sent to the customer (not yet Accepted), so saving a PO changes its status. */
    private function sentQuotation(): Quotation
    {
        $a = $this->admin();
        $this->actingAs($a)->post(route('crm.quotations.store'), $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($a)->post(route('crm.quotations.submit', $q));
        $this->actingAs($a)->post(route('crm.quotations.sent', $q));
        $q->refresh();
        $this->assertTrue(\App\Services\CustomerPoService::canTakePo($q), 'fixture quotation must be able to take a PO');
        $this->assertNotSame('Accepted', $q->status->value);

        return $q;
    }

    private function payload(Quotation $q, array $extra = []): array
    {
        return array_merge(['quotation_id' => $q->id, 'po_no' => 'PO/UP/1', 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026'], $extra);
    }

    private function postPo(Quotation $q, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->from(route('crm.customer-pos.create'))
            ->post(route('crm.customer-pos.store'), $this->payload($q, $extra));
    }

    /** Nothing of the PO is left: no PO, documents, logs or files, and the quotation and enquiry are as before. */
    private function assertNothingSaved(Quotation $q, string $statusBefore, string $enquiryBefore, int $logsBefore): void
    {
        $this->assertSame(0, CustomerPo::count());
        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
        $this->assertSame($statusBefore, $q->fresh()->status->value);
        $this->assertSame($enquiryBefore, $q->enquiry->fresh()->status->value);
        $this->assertSame($logsBefore, ActivityLog::count());
        $this->assertSame(0, ActivityLog::whereIn('action', ['customer_po.created', 'document.uploaded'])->count());
    }

    private function state(Quotation $q): array
    {
        return [$q->fresh()->status->value, $q->enquiry->fresh()->status->value, ActivityLog::count()];
    }

    public function test_new_po_form_has_the_document_upload_for_add_users_only(): void
    {
        $this->actingAs($this->admin())->get(route('crm.customer-pos.create'))->assertOk()
            ->assertSee('Upload PO / Agreement document')->assertSee('name="doc_files[]"', false)->assertSee('name="doc_category"', false)
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="_token"', false)
            ->assertSee('data-max-kb="'.\App\Http\Requests\Crm\DocumentRequest::maxKb('customer_po').'"', false);

        // a view-only user cannot open the new PO form at all
        $viewer = $this->user('poviewer', ['customer-po.view'], 'poview@company.example');
        $this->actingAs($viewer)->get(route('crm.customer-pos.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('crm.customer-pos.store'), [])->assertForbidden();
    }

    public function test_new_po_with_two_files_saves_the_po_documents_and_logs(): void
    {
        $q = $this->sentQuotation();
        $this->postPo($q, ['doc_category' => 'Agreement', 'doc_files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]])
            ->assertSessionHasNoErrors()->assertSessionHas('toast', fn ($t) => str_contains($t, 'with 2 documents'));

        $po = CustomerPo::firstOrFail();
        $docs = Document::where('documentable_type', $po->getMorphClass())->where('documentable_id', $po->id)->orderBy('id')->get();
        $this->assertSame(['a.pdf', 'b.pdf'], $docs->pluck('original_name')->all());
        $this->assertSame(['Agreement', 'Agreement'], $docs->pluck('category')->all());
        $this->assertSame([$this->admin()->id, $this->admin()->id], $docs->pluck('uploaded_by')->all());
        foreach ($docs as $d) {
            Storage::disk('local')->assertExists($d->path);
        }
        $this->assertSame('Accepted', $q->fresh()->status->value);
        $this->assertSame('PO received', $q->enquiry->fresh()->status->value);
        $this->assertSame(1, ActivityLog::where('action', 'customer_po.created')->where('subject_id', $po->id)->count());
        $this->assertSame(2, ActivityLog::where('action', 'document.uploaded')->where('subject_type', $po->getMorphClass())->where('subject_id', $po->id)->count());
    }

    public function test_files_without_a_document_type_are_refused_and_nothing_is_saved(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);
        $this->postPo($q, ['doc_files' => [$this->pdf('a.pdf')]])
            ->assertRedirect(route('crm.customer-pos.create'))->assertSessionHasErrors('doc_category')->assertSessionHasInput('po_no', 'PO/UP/1');
        $this->assertNothingSaved($q, $s, $e, $l);

        $this->postPo($q, ['doc_category' => 'Invoice', 'doc_files' => [$this->pdf('a.pdf')]])->assertSessionHasErrors('doc_category');
        $this->assertNothingSaved($q, $s, $e, $l);
    }

    public function test_a_bad_type_or_oversize_file_refuses_the_whole_save(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);

        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [$this->pdf('a.pdf'), UploadedFile::fake()->create('x.exe', 5)]])
            ->assertSessionHasErrors(['doc_files.1' => 'Upload a PDF, image (JPG, PNG, WEBP), Word, Excel, CSV, text or email file.']);
        $this->assertNothingSaved($q, $s, $e, $l);

        // a text file renamed to .pdf: the content check of the Upload box applies here too
        file_put_contents($this->dir.'/fake.pdf', 'just some text');
        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [new UploadedFile($this->dir.'/fake.pdf', 'fake.pdf', null, null, true)]])
            ->assertSessionHasErrors('doc_files.0');
        $this->assertNothingSaved($q, $s, $e, $l);

        $kb = \App\Http\Requests\Crm\DocumentRequest::maxKb('customer_po');
        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [UploadedFile::fake()->create('big.pdf', $kb + 1, 'application/pdf')]])
            ->assertSessionHasErrors(['doc_files.0' => 'Each document must not be larger than '.\App\Support\UploadLimit::label($kb).'.']);
        $this->assertNothingSaved($q, $s, $e, $l);

        // the error is shown on the form, with the entries kept
        $this->actingAs($this->admin())->get(route('crm.customer-pos.create'))->assertOk()
            ->assertSee('Each document must not be larger than')->assertSee('Choose the files again.')->assertSee('PO/UP/1');
    }

    public function test_more_than_five_files_are_refused(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);
        $files = array_map(fn ($i) => $this->pdf("f{$i}.pdf"), range(1, 6));
        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => $files])
            ->assertSessionHasErrors(['doc_files' => 'Choose up to 5 documents here; upload more on the saved PO.']);
        $this->assertNothingSaved($q, $s, $e, $l);

        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => array_slice($files, 0, 5)])->assertSessionHasNoErrors();
        $this->assertSame(5, Document::count());
    }

    public function test_a_failure_on_the_second_file_rolls_everything_back(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);
        $this->app->instance(DocumentService::class, new class extends DocumentService
        {
            public int $n = 0;

            public function upload(Model $record, string $category, UploadedFile $file, User $actor): Document
            {
                if (++$this->n === 2) {
                    throw new \RuntimeException('disk full');
                }

                return parent::upload($record, $category, $file, $actor);
            }
        });

        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]])
            ->assertRedirect(route('crm.customer-pos.create'))
            ->assertSessionHasErrors(['doc_files' => 'The PO was not saved because a document could not be stored. Check the entries, choose the files again and save.'])
            ->assertSessionHasInput('po_no', 'PO/UP/1');
        $this->assertNothingSaved($q, $s, $e, $l);
    }

    /** The disk itself throws on the second write (a disk set to throw, as S3 may): the real DocumentService path. */
    public function test_a_disk_that_throws_on_the_second_file_rolls_everything_back(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);
        $this->failingDisk(throw: true);

        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]])
            ->assertRedirect(route('crm.customer-pos.create'))->assertSessionHasErrors('doc_files');
        $this->assertNothingSaved($q, $s, $e, $l);
    }

    /**
     * The default local disk has 'throw' => false: a failed write makes store() return false instead of throwing.
     * The save must still be all or nothing; no document row may point at a file that was never stored.
     */
    public function test_a_disk_that_fails_quietly_on_the_second_file_saves_nothing(): void
    {
        $q = $this->sentQuotation();
        [$s, $e, $l] = $this->state($q);
        $this->failingDisk(throw: false);

        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]]);
        $this->assertSame(0, Document::where('path', '')->count(), 'a document row was saved with an empty path for a file that was not stored');
        $this->assertNothingSaved($q, $s, $e, $l);
    }

    private function failingDisk(bool $throw): void
    {
        $root = Storage::disk('local')->path('');
        $adapter = new class($root) extends LocalFilesystemAdapter
        {
            public int $writes = 0;

            public function writeStream(string $path, $contents, Config $config): void
            {
                if (++$this->writes === 2) {
                    throw UnableToWriteFile::atLocation($path, 'disk full');
                }
                parent::writeStream($path, $contents, $config);
            }

            public function write(string $path, string $contents, Config $config): void
            {
                if (++$this->writes === 2) {
                    throw UnableToWriteFile::atLocation($path, 'disk full');
                }
                parent::write($path, $contents, $config);
            }
        };
        Storage::set('local', new FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $root, 'throw' => $throw]));
    }

    public function test_a_new_po_without_files_saves_as_before(): void
    {
        $q = $this->sentQuotation();
        $this->postPo($q, ['doc_category' => 'Customer PO'])->assertSessionHasNoErrors()
            ->assertSessionHas('toast', fn ($t) => str_ends_with($t, 'saved'));
        $po = CustomerPo::firstOrFail();
        $this->assertSame(0, Document::count());
        $this->assertSame('Accepted', $q->fresh()->status->value);
        $this->assertSame(1, ActivityLog::where('action', 'customer_po.created')->where('subject_id', $po->id)->count());
        $this->assertSame(0, ActivityLog::where('action', 'document.uploaded')->count());

        // without doc_category at all, too (old form / API)
        $this->postPo($q, ['po_no' => 'PO/UP/2'])->assertSessionHasNoErrors();
        $this->assertSame(2, CustomerPo::count());

        // editing a saved PO ignores document fields (they are validated on create only)
        $this->actingAs($this->admin())->put(route('crm.customer-pos.update', $po), $this->payload($q, ['po_value' => '500', 'doc_category' => 'Nope']))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Document::count());
    }

    public function test_saved_po_box_upload_for_edit_users_view_only_can_download_and_no_delete(): void
    {
        $q = $this->sentQuotation();
        $this->postPo($q, ['doc_category' => 'Customer PO', 'doc_files' => [$this->pdf('a.pdf')]])->assertSessionHasNoErrors();
        $po = CustomerPo::firstOrFail();
        $doc = Document::firstOrFail();
        $upload = 'action="'.route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]).'"';
        $delete = 'action="'.route('crm.documents.destroy', $doc).'"';

        // Admin (edit): box above the form, Upload, the file listed, no row Delete, and no new-PO upload block
        $html = $this->actingAs($this->admin())->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('PO / Agreement documents')->assertSee('Upload PO / Agreement document')->assertSee($upload, false)
            ->assertSee('a.pdf')->assertDontSee($delete, false)->assertDontSee('Delete a.pdf')
            ->assertDontSee('name="doc_files[]"', false)->getContent();
        $this->assertLessThan(strpos($html, 'id="poForm"'), strpos($html, 'id="documents"'), 'documents box should be above the PO form');

        $editor = $this->user('poeditor', ['customer-po.view', 'customer-po.edit'], 'poedit@company.example');
        $this->actingAs($editor)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee($upload, false)->assertDontSee($delete, false);
        $this->actingAs($editor)->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), ['category' => 'Agreement', 'file' => $this->pdf('c.pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Document::count());

        // view only: sees and downloads, no Upload, cannot upload by posting either
        $viewer = $this->user('poviewer', ['customer-po.view'], 'poview2@company.example');
        $this->actingAs($viewer)->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('PO / Agreement documents')->assertSee('a.pdf')->assertSee(route('crm.documents.download', $doc), false)
            ->assertDontSee('Upload PO / Agreement document')->assertDontSee($upload, false)->assertDontSee($delete, false);
        $this->actingAs($viewer)->get(route('crm.documents.download', $doc))->assertOk();
        $this->actingAs($viewer)->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), ['category' => 'Agreement', 'file' => $this->pdf('d.pdf')])
            ->assertForbidden();
        $this->actingAs($viewer)->delete(route('crm.documents.destroy', $doc))->assertForbidden();
        $this->assertSame(2, Document::count());
    }
}
