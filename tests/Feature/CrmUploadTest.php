<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\FinancialYear;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CRM uploads (Sat 18/10/2026: "check upload properly working for CRM module"). Real file contents are used, so the
 * content check runs on what PHP actually detects: allowed and refused types, renamed files, size limit, upload,
 * view back, delete, the activity log and who may do what, on enquiries, site visits and PO / agreements.
 */
class CrmUploadTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/crm-upload-test-'.uniqid();
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

    private function user(string $role, array $permissions, string $email): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        $u = User::create(['name' => ucfirst($role), 'email' => $email, 'mobile' => '98220'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** A real file on disk, so PHP's fileinfo sees its content. */
    private function file(string $name, string $content): UploadedFile
    {
        file_put_contents($this->dir.'/'.$name, $content);

        return new UploadedFile($this->dir.'/'.$name, $name, null, null, true);
    }

    private function samples(): array
    {
        $ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 504);

        return [
            'brief.pdf' => "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
            'card.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
            'plants.csv' => "Name,Qty\nFern,10\n",
            'notes.txt' => "Lobby needs low-light plants.\n",
            'mail.eml' => "From: a@example.com\r\nTo: b@example.com\r\nSubject: Requirement\r\nDate: Sat, 18 Oct 2026 10:00:00 +0530\r\nMIME-Version: 1.0\r\nContent-Type: text/plain\r\n\r\nPlease quote.\r\n",
            // Outlook emails and old Word / Excel files are OLE containers, often reported only as "CDFV2".
            'mail.msg' => $ole,
            'old.doc' => $ole,
            'old.xls' => $ole,
        ];
    }

    public function test_allowed_types_upload_and_open_again_and_refused_types_never_reach_storage(): void
    {
        $e = $this->crmEnquiry();
        $admin = $this->admin();
        $url = "/crm/documents/enquiry/{$e->id}";

        foreach ($this->samples() as $name => $content) {
            $this->actingAs($admin)->post($url, ['category' => 'Other', 'file' => $this->file($name, $content)])->assertSessionHasNoErrors();
            $doc = Document::latest('id')->first();
            $this->assertSame($name, $doc->original_name, $name);
            Storage::disk('local')->assertExists($doc->path);
            $res = $this->actingAs($admin)->get('/crm/documents/'.$doc->id)->assertOk();
            $inline = in_array($name, ['brief.pdf', 'card.png'], true);
            $this->assertStringStartsWith($inline ? 'inline' : 'attachment', $res->headers->get('Content-Disposition'), $name);
            $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        }
        $this->assertSame(count($this->samples()), Document::count());
        $this->assertSame(count($this->samples()), DB::table('activity_logs')->where('action', 'document.uploaded')->count());

        $refused = [
            'virus.exe' => "MZ\x90\0".str_repeat("\0", 60),
            'shell.php' => "<?php echo 'x';",
            'page.html' => '<html><body>x</body></html>',
            'fake.pdf' => "Not really a PDF\n",           // text renamed to .pdf
            'fake.png' => "%PDF-1.4\n%%EOF\n",             // PDF renamed to .png
            'fake.docx' => "plain text\n",                  // text renamed to .docx
            'image.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
        ];
        foreach ($refused as $name => $content) {
            $this->actingAs($admin)->post($url, ['category' => 'Other', 'file' => $this->file($name, $content)]);
            $this->assertTrue(session()->has('errors') && session('errors')->has('file'), $name.' was accepted');
        }
        // over 10 MB, a wrong document type, and no file at all
        $this->actingAs($admin)->post($url, ['category' => 'Other', 'file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post($url, ['category' => 'Site photo', 'file' => $this->file('ok.pdf', "%PDF-1.4\n%%EOF\n")])->assertSessionHasErrors('category');
        $this->actingAs($admin)->post($url, ['category' => 'Other'])->assertSessionHasErrors('file');
        $this->assertSame(count($this->samples()), Document::count());
        $this->assertCount(count($this->samples()), Storage::disk('local')->allFiles('documents'));
    }

    public function test_replace_by_delete_and_upload_removes_the_old_file_and_logs_both(): void
    {
        $e = $this->crmEnquiry();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Visiting card', 'file' => $this->file('card.png', $this->samples()['card.png'])])->assertSessionHasNoErrors();
        $old = Document::firstOrFail();
        $this->actingAs($admin)->delete('/crm/documents/'.$old->id)->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old->path);
        $this->assertNull(Document::find($old->id));
        $this->actingAs($admin)->get('/crm/documents/'.$old->id)->assertNotFound();
        $this->actingAs($admin)->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Visiting card', 'file' => $this->file('card2.pdf', $this->samples()['brief.pdf'])])->assertSessionHasNoErrors();
        $this->assertSame(1, Document::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.deleted', 'subject_id' => $e->id]);
        $this->actingAs($admin)->get('/crm/enquiries/'.$e->id)->assertOk()->assertSee('card2.pdf');
    }

    public function test_site_visit_and_po_take_uploads_and_rights_and_units_are_checked(): void
    {
        $e = $this->crmEnquiry();
        $admin = $this->admin();
        $visit = SiteVisit::forceCreate(['unit_id' => $e->unit_id, 'enquiry_id' => $e->id, 'visit_at' => now(), 'status' => 'Planned']);
        $poId = DB::table('customer_pos')->insertGetId(['unit_id' => $e->unit_id, 'financial_year_id' => FinancialYear::forDate(today())->id, 'enquiry_id' => $e->id,
            'po_no' => 'PO/1', 'po_date' => today(), 'po_value' => 100, 'received_date' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $pdf = fn ($n) => $this->file($n, $this->samples()['brief.pdf']);

        $this->actingAs($admin)->post("/crm/documents/site_visit/{$visit->id}", ['category' => 'Site photo', 'file' => $this->file('lobby.png', $this->samples()['card.png'])])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/crm/documents/customer_po/{$poId}", ['category' => 'Customer PO', 'file' => $pdf('po.pdf')])->assertSessionHasNoErrors();
        $this->assertSame(['site_visit', 'customer_po'], Document::orderBy('id')->pluck('documentable_type')->all());

        // view only: can open, cannot upload or delete
        $viewer = $this->user('Viewer', ['enquiry.view'], 'viewer@company.example');
        $this->actingAs($admin)->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Other', 'file' => $pdf('a.pdf')]);
        $doc = Document::latest('id')->first();
        $this->actingAs($viewer)->get('/crm/documents/'.$doc->id)->assertOk();
        $this->actingAs($viewer)->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Other', 'file' => $pdf('b.pdf')])->assertForbidden();
        $this->actingAs($viewer)->delete('/crm/documents/'.$doc->id)->assertForbidden();
        // no CRM rights at all: cannot even open it
        $buyer = $this->user('Buyer', [], 'buyer@company.example');
        $this->actingAs($buyer)->get('/crm/documents/'.$doc->id)->assertForbidden();
        // another unit's record: refused
        $other = Unit::create(['name' => 'Unit 9', 'code' => 'U9', 'is_active' => true]);
        DB::table('enquiries')->where('id', $e->id)->update(['unit_id' => $other->id]);
        $this->actingAs($viewer)->get('/crm/documents/'.$doc->id)->assertForbidden();
        $this->actingAs($admin->fresh())->get('/crm/documents/'.$doc->id)->assertForbidden();
        Storage::disk('local')->assertExists($doc->path);
    }
}
