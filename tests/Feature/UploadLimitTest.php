<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Support\UploadLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin > Config maximum upload size (22/10/2026): default 1 MB, set by an Admin from 0.5 to 50 MB with one decimal,
 * applied to the Prospect CSV import and enquiry documents; other uploads keep their own limits.
 */
class UploadLimitTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function userWith(string $role, array $permissions, string $email): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        $u = User::create(['name' => $role, 'email' => $email, 'mobile' => '98220'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function pdf(int $kb): UploadedFile
    {
        return UploadedFile::fake()->create('doc.pdf', $kb, 'application/pdf');
    }

    private function csv(int $kb): UploadedFile
    {
        return UploadedFile::fake()->create('p.csv', $kb, 'text/csv');
    }

    private function enquiryUpload(int $kb)
    {
        $e = $this->crmEnquiry();

        return $this->actingAs($this->admin())->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Other', 'file' => $this->pdf($kb)]);
    }

    private function prospectImport(int $kb)
    {
        return $this->actingAs($this->admin())->post('/crm/prospects/import', ['file' => $this->csv($kb)]);
    }

    /** The 'file' errors in the session (empty when none). */
    private function fileErrors(): array
    {
        $bag = session('errors');

        return $bag ? $bag->get('file') : [];
    }

    private function assertNoSizeError(): void
    {
        foreach ($this->fileErrors() as $m) {
            $this->assertStringNotContainsString('larger than', $m);
            $this->assertStringNotContainsString('at most', $m);
        }
    }

    private function setMb(string $mb)
    {
        return $this->actingAs($this->admin())->put('/admin/config', ['upload_max_mb' => $mb]);
    }

    public function test_default_is_1_mb(): void
    {
        $this->assertSame(1024, UploadLimit::kb());
        $this->assertSame('1 MB', UploadLimit::label());
        $this->assertSame('max:1024', UploadLimit::rule());
        $this->assertSame('The file must not be larger than 1 MB.', UploadLimit::message());
        $this->assertSame('Max 1 MB', UploadLimit::hint());
        $this->actingAs($this->admin())->get('/admin/config')->assertOk()->assertSee('value="1"', false);
    }

    public function test_label_rounding(): void
    {
        $this->assertSame('0.5 MB', UploadLimit::label(512));
        $this->assertSame('2.5 MB', UploadLimit::label(2560));
        $this->assertSame('0.7 MB', UploadLimit::label(717)); // round(0.7 * 1024)
        $this->assertSame('50 MB', UploadLimit::label(51200));
    }

    public function test_prospect_import_over_1_mb_is_refused_and_up_to_1_mb_passes_the_size_check(): void
    {
        $this->prospectImport(1025)->assertSessionHasErrors(['file' => 'The file must not be larger than 1 MB.']);
        $this->prospectImport(1024);
        $this->assertNoSizeError();
        $this->prospectImport(500);
        $this->assertNoSizeError();

        // a real small file is imported
        $csv = "Prospect name,Domestic / Export,Contact no 1,Contact no 2\nOrion Agro Equipment,Domestic,+91 98220 11111,\n";
        $this->actingAs($this->admin())->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
            ->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
    }

    public function test_enquiry_document_over_1_mb_is_refused_and_up_to_1_mb_is_saved(): void
    {
        $this->enquiryUpload(1100)->assertSessionHasErrors(['file' => 'The file must not be larger than 1 MB.']);
        $this->enquiryUpload(1024)->assertSessionHasNoErrors();
        $this->enquiryUpload(800)->assertSessionHasNoErrors();
    }

    public function test_after_admin_sets_2_5_mb_a_2_mb_file_is_taken_on_both(): void
    {
        $this->setMb('2.5')->assertSessionHasNoErrors()->assertRedirect('/admin/config');
        $this->assertDatabaseHas('app_settings', ['key' => 'upload_max_kb', 'value' => '2560']);
        $this->assertSame(2560, UploadLimit::kb());

        $this->enquiryUpload(2048)->assertSessionHasNoErrors();
        $this->prospectImport(2048);
        $this->assertNoSizeError();

        $this->enquiryUpload(2600)->assertSessionHasErrors(['file' => 'The file must not be larger than 2.5 MB.']);
        $this->prospectImport(2600)->assertSessionHasErrors(['file' => 'The file must not be larger than 2.5 MB.']);

        $this->actingAs($this->admin())->get('/admin/config')->assertOk()->assertSee('value="2.5"', false)
            ->assertSee('The file must not be larger than 2.5 MB.');
        $e = $this->crmEnquiry();
        $page = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertStringContainsString('data-max-kb="2560"', $page);
        $this->assertStringContainsString('Max 2.5 MB', $page);
    }

    public function test_other_uploads_keep_their_own_limits(): void
    {
        $e = $this->crmEnquiry();
        $visit = SiteVisit::forceCreate(['unit_id' => $e->unit_id, 'enquiry_id' => $e->id, 'visit_at' => now(), 'status' => 'Planned']);
        $url = "/crm/documents/site_visit/{$visit->id}";
        $this->actingAs($this->admin())->post($url, ['category' => 'Site photo', 'file' => $this->pdf(9000)])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post($url, ['category' => 'Site photo', 'file' => $this->pdf(10241)])
            ->assertSessionHasErrors(['file' => 'The file is larger than 10 MB.']);

        $q = fn (int $kb) => $this->actingAs($this->admin())->post('/crm/quotations/import', ['file' => $this->csv($kb)]);
        $q(1500);
        $this->assertNoSizeError();
        $q(2049)->assertSessionHasErrors(['file' => 'The file can be at most 2 MB.']);
        $this->assertStringContainsString('data-max-kb="2048"', $this->actingAs($this->admin())->get('/crm/quotations/create')->getContent());

        // still the same after the setting changes
        $this->setMb('0.5')->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post($url, ['category' => 'Site photo', 'file' => $this->pdf(9000)])->assertSessionHasNoErrors();
        $q(1500);
        $this->assertNoSizeError();
        $this->enquiryUpload(600)->assertSessionHasErrors(['file' => 'The file must not be larger than 0.5 MB.']);
    }

    public function test_invalid_values_are_refused_and_edges_accepted(): void
    {
        foreach (['0.4', '51', '1.25', 'abc', '', '-1', '1.', '50.1', '0'] as $bad) {
            $this->setMb($bad)->assertSessionHasErrors('upload_max_mb');
        }
        $this->assertDatabaseCount('app_settings', 0);
        $this->assertSame(1024, UploadLimit::kb());

        $this->setMb('0.5')->assertSessionHasNoErrors();
        $this->assertSame(512, UploadLimit::kb());
        $this->setMb('50')->assertSessionHasNoErrors();
        $this->assertSame(51200, UploadLimit::kb());
        $this->setMb('1,5')->assertSessionHasNoErrors();
        $this->assertSame(1536, UploadLimit::kb());
        $this->setMb(' 3 ')->assertSessionHasNoErrors();
        $this->assertSame(3072, UploadLimit::kb());
    }

    public function test_only_config_edit_can_save_and_config_view_is_read_only(): void
    {
        // seeded Sales role: no config rights
        $sales = User::create(['name' => 'Sales X', 'email' => 'sales-x@company.example', 'mobile' => '9822033333', 'password' => 'Secret2026', 'status' => 'Active']);
        $sales->assignRole('Sales');
        $sales->units()->sync([Unit::first()->id]);
        $this->assertFalse($sales->can('config.view'));
        $this->actingAs($sales)->get('/admin/config')->assertForbidden();
        $this->actingAs($sales)->get('/admin/config')->assertForbidden();
        $this->actingAs($sales)->put('/admin/config', ['upload_max_mb' => '5'])->assertForbidden();

        $viewer = $this->userWith('Config viewer', ['config.view'], 'viewer@company.example');
        $this->actingAs($viewer)->get('/admin/config')->assertOk()->assertDontSee('Save config');
        $this->actingAs($viewer)->put('/admin/config', ['upload_max_mb' => '5'])->assertForbidden();

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertSame(1024, UploadLimit::kb());

        $this->actingAs($this->admin())->get('/admin/config')->assertOk()->assertSee('Save config');
        $this->actingAs($this->admin())->get('/app/config')->assertRedirect('/admin/config');
    }

    public function test_change_is_activity_logged_once(): void
    {
        $this->setMb('2.5')->assertSessionHasNoErrors();
        $log = ActivityLog::where('action', 'settings.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Changed Maximum upload size to 2.5 MB', $log->description);
        $this->assertSame($this->admin()->id, $log->user_id);
        $this->assertEquals(['Maximum upload size' => ['old' => '1 MB', 'new' => '2.5 MB']], $log->changes);

        // saving the same value again logs nothing
        $this->setMb('2.5')->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityLog::where('action', 'settings.updated')->count());

        $this->actingAs($this->admin())->get("/admin/activity-log/{$log->id}")->assertOk()->assertSee('Config changed');
    }

    public function test_hint_is_shown_on_both_pages(): void
    {
        $e = $this->crmEnquiry();
        $page = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertStringContainsString('data-max-kb="1024"', $page);
        $this->assertStringContainsString('Max 1 MB', $page);

        $p = $this->actingAs($this->admin())->get('/crm/prospects')->assertOk()->getContent();
        $this->assertStringContainsString('data-max-kb="1024"', $p);
        $this->assertMatchesRegularExpression('/[Mm]ax 1 MB/', $p);
    }
}
