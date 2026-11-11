<?php

namespace Tests\Feature;

use App\Mail\QuotationMail;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Company logo (23/10/2026): uploaded in Admin > Masters > Company, kept on the private disk and printed at the top
 * left of the quotation PDF (also the emailed one). Test database only.
 */
class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    /** A real 300 x 120 baseline JPEG (2 KB); GD is not needed to build it. */
    private const JPG = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAeAEsAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A4GiiirPmgooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigC/pGkXet34tLQICFMkssjbY4Yx953b+FR3P4ckgVf/4RDVf7e/snZFu8vz/tXmf6P5HXz/M6eXjnP4YzxWj4YvdNtvBfiqDUH3ef9k2W6XCxSy7ZGJ2EhunBPynj06109hruhp4a0fTorpLUXmm6lYhbi4WQ20ksiFDMyqNqnaedvAI7AmkdEKUGld+f42scfp3gq61i4to9M1TTbtZ5mgMiPIoicRtIAysgbDKjYIBGVIyKmt/hx4jlt5pri2SyWK6itcXJKl3eRUBXAOVBYfN0I6ZxWl4GsLXQfFel6jqesabC3nSoIku45AE8l8uzqxVBuKqATkkn050vCfiuO902ZNTubK1a1bSbWAFwheKG4JLHceSASSRwB6UFwp02lzaPX8jh9V0KLSZLqGTWdPmuraQxPbwrPu3BtpALRheOe/bjNY9df4zN5d6hqdx5uiyWP2ySWJ7WSz851LkKT5Z8xuDk5z6npmuQoOeokpWRpavotzov2D7S8T/bbOO8j8sk4R84ByBzx7/WrOn+Gp7ywhvrm+stOtribybeS9dlEzfxFdqn5V4BY4UE4z1rd8U2f9tQ+HpbC902RYNFtoJQ+owRski7iVKs4ORkdqhIg8R+DfD+nW19ZW1zpk08dwt7cLANsr7lkUsfmUYIOPmB7HINBp7NKT/Dz2MtfCWpRfbm1IxaXDZSCGWa83BTIcYRdgYuSDu+UEbec4IyR+Fbma5mEV/p72UFutzNqCzEwRow+UNxuDk/KEK7ie2Oa6/xPr2m+MLHWbPTbqKKb+1I72L7ZIsCzxrAsJ2s5AzkbsMQdp9cgMuJfD8el6r4f0yWytLvUNNs5HxclrYXMbtJJGJWZgCQQB823IwSDQW6UL6bfnucPq2i3OkfZpJHintbqPzLe6gJaKUdwCQCCp4KkAg9R0rNrqfE93bR+HPDuhx3EVxdadHO9w8DiSJTK4cIHHDEDrjIzwCea5agwqJKVkFFFFMgKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA/9k=';

    /** A real PNG; used when GD is off (UploadedFile::fake()->image() needs GD). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAMgAAADICAYAAACtWK6eAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAE70lEQVR4nO3dTW7cRhSF0ZssxOvI/kdZhzfiDAJCgoR+6mYXWX/nLMAu0vhwixrICQAAAAAAAADw4a/eB9jNv79+/Xn3z/jn92//bjfxoi/QIoKzxNOWl/mmnjE8SzTneXEvmiGInwjmeV7UE1aI4hGx1LycB1aO4hGxfOeFfLJjFI+I5X9eQoRR2T2UrR9eGM/bNZQtH1oY5+0WylYPK4x2dglli4cUxnVWD2XphxPGfVYNZcmHSsTRw4qRLPdAwuhvpVCWeZBEHCNZJZIlHkIY45o9lKkPn4hjBjNHMu3BhTGfGUOZ7sCJOGY2WyRTHTYRxwpmimSagybiWMkskUxxSGGsa/RQhj5cIo4djBzJsAdLxLGTUSMZ8lCJOHY0YiTDHSgRx85Gi2SowyTiYKxIhjlIIg4+jBLJEIdIxMF3I0TS/QCJOHisdyTdAxEHP+kZyd+9/mKYQdcFsR48q9eKdAtEHLyqRyRdAhEHZ90dye2BiIN33RmJj3Qo3Log1oNW7lqR2wIRB63dEYkrFhRuWRDrwVWuXpHLAxEHV7syElcsKFy6INaDu1y1IpcFIg7udkUkrlhQuGRBrAe9tF4RCwKF5gtiPeit5YpYECg0XRDrwSharYgFgUKzBbEejKbFilgQKDRZEOvBqN5dEQsChbcXxHowundWxIJAQSBQeOuK5XrFLM5esywIFAQChdNXLNcrZnPmmmVBoCAQKJy6YrleMatXr1kWBAoCgYJAoPDyN4jvD2b3yneIBYGCQKAgECgIBAovfaT7QGcVz36oWxAoCAQKAoGCQKAgECgIBApP/5jXj3hZzTM/6rUgUBAIFAQCBYFAQSBQEAgUBAIFgUBBIFAQCBQEAgWBQEEgUBAIFAQCBYFAwe/FYkt+LxY0IBAoCAQKAoGCQKAgECj4PwrZjv+jEBoRCBQEAgWBQOHlj/TEhzrzeuUDPbEgUBIIFAQChVPfIInvEObz6vdHYkGgJBAonL5iJa5ZzOPM9SqxIFASCBTeumIlrlmM7+z1KrEgUBIIFN6+YiWuWYzrnetVYkGg1GRBEivCeN5dj8SCQKnZgiRWhHG0WI/EgkCp6YIkVoT+Wq1HYkGg1HxBEitCPy3XI7EgULpkQRIrwv1ar0diQaB02YIkVoT7XLEeycWBJCLhelfFkbhiQenyBUmsCNe5cj2SmwJJREJ7V8eRuGJB6bYFSawI7dyxHsnNgSQi4X13xZG4YkHp9gVJrAjn3bkeSadAEpHwurvjSDoGkoiE5/WII+kcSCISftYrjsRHOpS6L0hiRXis53okgwSSiITveseRDBRIIhI+jBBHMlggiUgYJ45kwEASkexspDiSQQNJRLKj0eJIBg4kEclORowjGTyQRCQ7GDWOZIJAEpGsbOQ4kkkCOQhlHaOHcZjikJ+JZH6zxJFMGEgikpnNFEcyaSCJSGY0WxzJxIEchDK+GcM4THvwz0QyrpnjSBYJ5CCUccwexmGJh/hMJP2tEkeyYCAHodxvpTAOyz3QZyK5z4pxJIsHchDKdVYN47D0w30llHZWD+OwxUN+JZTzdgnjsNXDfiWU5+0WxmHLh/5KKI/tGsZh64f/Sigfdg/j4CU8sGMsovjOC3nCyrGIoublvGiFWETxPC/qTTMEI4jzvLgL9IxGDG15mTdrEY8IAAAAAAAAAC70H7NhrQ+rKA/cAAAAAElFTkSuQmCC';

    /** Offset of the SOF0 marker in JPG: height at +5, width at +7 (big-endian). */
    private const SOF = 222;

    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['nursery.documents_disk' => 'local']);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function jpgBytes(?int $w = null, ?int $h = null): string
    {
        $b = base64_decode(self::JPG);
        if ($w && $h) {
            $b = substr_replace($b, pack('nn', $h, $w), self::SOF + 5, 4);
        }

        return $b;
    }

    private function jpg(string $name = 'logo.jpg', ?int $w = null, ?int $h = null): UploadedFile
    {
        return $this->upload($name, $this->jpgBytes($w, $h));
    }

    /**
     * A real uploaded file, so its type is read from the content as on a live upload. (UploadedFile::fake() files
     * report the type of their name, so they cannot show a spoofed extension being caught.)
     */
    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $bytes);
        $this->temp[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    private function sales(): User
    {
        $u = User::where('email', 'priya@company.example')->first();
        if (! $u) {
            $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
            $u->assignRole('Sales');
            $u->units()->sync([Unit::first()->id]);
        }

        return $u;
    }

    private function base(array $over = []): array
    {
        return array_merge(['name' => 'Acme Nursery', 'gstin' => '', 'pan' => '', 'address' => 'Pune', 'phone' => '', 'email' => ''], $over);
    }

    private function save(array $payload, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->put('/admin/masters/company', $payload);
    }

    private function quotation(): Quotation
    {
        $this->actingAs($this->admin())->post('/crm/quotations', $this->newQuotationPayload())->assertSessionHasNoErrors();

        return Quotation::latest('id')->first();
    }

    /** The PDF view's HTML, loaded as QuotationController::pdf does. */
    private function pdfHtml(Quotation $q): string
    {
        return view('pdf.quotation', ['q' => $q->fresh(['lines.taxStructure', 'party', 'prospect', 'salesPerson', 'preparer', 'currency', 'unit', 'enquiry', 'taxStructure'])])->render();
    }

    private function companyFiles(): array
    {
        return Storage::disk('local')->allFiles('company');
    }

    public function test_upload_jpg_is_stored_previewed_logged_and_printed_in_the_pdf(): void
    {
        $this->actingAs($this->admin())->get('/admin/masters/company')->assertOk()
            ->assertSee('Company logo')->assertSee('enctype="multipart/form-data"', false)->assertDontSee('Remove logo');

        $this->save($this->base(['logo' => $this->jpg('../../..\\evil name.jpg')]))->assertSessionHasNoErrors()->assertRedirect('/admin/masters/company');

        $c = Company::current();
        $this->assertNotNull($c->logo_path);
        $this->assertMatchesRegularExpression('#^company/[A-Za-z0-9]{40}\.jpg$#', $c->logo_path, 'stored under company/ with a random name, never the client name');
        Storage::disk('local')->assertExists($c->logo_path);
        $this->assertSame($this->jpgBytes(), Storage::disk('local')->get($c->logo_path));
        $this->assertSame([$c->logo_path], $this->companyFiles());

        $log = ActivityLog::where('action', 'company.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->changes['Logo']['old']);
        $this->assertNotNull($log->changes['Logo']['new']);

        $uri = 'data:image/jpeg;base64,'.base64_encode($this->jpgBytes());
        $this->actingAs($this->admin())->get('/admin/masters/company')->assertOk()
            ->assertSee('src="'.$uri.'"', false)->assertSee('Remove logo');

        // 300 x 120 fits 60 high: 150 x 60
        $q = $this->quotation();
        $html = $this->pdfHtml($q);
        $this->assertStringContainsString('<img src="'.$uri.'" width="150" height="60" alt="">', $html);

        $pdf = $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}/pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_pdf_logo_size_keeps_shape_and_fits_60_by_200(): void
    {
        foreach ([[300, 120, 150, 60], [1000, 20, 200, 4], [50, 500, 6, 60], [40, 30, 40, 30], [4000, 4000, 60, 60]] as [$w, $h, $ew, $eh]) {
            $this->save($this->base(['logo' => $this->jpg('logo.jpg', $w, $h)]))->assertSessionHasNoErrors();
            $logo = Company::current()->pdfLogo();
            $this->assertNotNull($logo, "{$w}x{$h}");
            $this->assertEquals([$ew, $eh], [$logo['width'], $logo['height']], "{$w}x{$h}");
        }
        $this->assertCount(1, $this->companyFiles(), 'each replace deletes the old file');
    }

    public function test_replace_deletes_old_file_and_remove_clears_column_file_and_pdf_logo(): void
    {
        $q = $this->quotation();
        $before = $this->pdfHtml($q);
        $this->assertStringNotContainsString('<img', $before);
        $this->assertMatchesRegularExpression('#<table class="head">\s*<tr>\s*<td><h1>Quotation</h1>#', $before, 'no logo: header as before');

        $this->save($this->base(['logo' => $this->jpg()]))->assertSessionHasNoErrors();
        $first = Company::current()->logo_path;

        $this->save($this->base(['logo' => $this->jpg('second.jpg', 200, 50)]))->assertSessionHasNoErrors();
        $second = Company::current()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        $this->assertSame([$second], $this->companyFiles());
        $this->assertSame('Previous logo', ActivityLog::where('action', 'company.updated')->latest('id')->first()->changes['Logo']['old']);

        // saving other fields without a file keeps the logo
        $this->save($this->base(['address' => 'Nashik']))->assertSessionHasNoErrors();
        $this->assertSame($second, Company::current()->logo_path);
        $this->assertNull(ActivityLog::where('action', 'company.updated')->latest('id')->first()->changes['Logo'] ?? null);

        $this->save($this->base(['remove_logo' => '1']))->assertSessionHasNoErrors();
        $this->assertNull(Company::current()->logo_path);
        $this->assertNull(Company::query()->value('logo_path'));
        Storage::disk('local')->assertMissing($second);
        $this->assertSame([], $this->companyFiles());
        $log = ActivityLog::where('action', 'company.updated')->latest('id')->first();
        $this->assertSame('Previous logo', $log->changes['Logo']['old']);
        $this->assertNull($log->changes['Logo']['new']);

        $after = $this->pdfHtml($q);
        $this->assertStringNotContainsString('<img', $after);
        $this->assertSame($before, $after, 'no logo again: same HTML as before the logo');
        $this->assertStringNotContainsString('/Subtype /Image', $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}/pdf")->getContent());
    }

    public function test_failed_save_removes_the_new_file_and_keeps_the_old_logo(): void
    {
        $this->save($this->base(['logo' => $this->jpg()]))->assertSessionHasNoErrors();
        $old = Company::current()->logo_path;

        Company::saving(fn () => throw new \RuntimeException('database down'));
        $this->withoutExceptionHandling();
        try {
            app(\App\Services\CompanyService::class)->save($this->base(), $this->admin(), $this->jpg('new.jpg', 100, 40));
            $this->fail('save should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('database down', $e->getMessage());
        }
        Company::flushEventListeners();

        $this->assertSame($old, Company::query()->value('logo_path'));
        $this->assertSame([$old], $this->companyFiles(), 'new file deleted, old one kept');
    }

    public function test_missing_logo_file_prints_without_logo(): void
    {
        $this->save($this->base(['logo' => $this->jpg()]))->assertSessionHasNoErrors();
        Storage::disk('local')->delete(Company::current()->logo_path);
        $this->assertNull(Company::current()->pdfLogo());
        $this->assertStringNotContainsString('<img', $this->pdfHtml($this->quotation()));
        $this->actingAs($this->admin())->get('/admin/masters/company')->assertOk()->assertDontSee('Remove logo');
    }

    public function test_svg_html_text_and_spoofed_files_are_refused(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';
        $cases = [
            'svg' => $this->upload('logo.svg', $svg),
            'svg named .jpg' => $this->upload('logo.jpg', $svg),
            'html named .jpg' => $this->upload('logo.jpg', '<html><body><script>alert(1)</script></body></html>'),
            'text named .jpg' => $this->upload('logo.jpg', 'this is just some text, not an image'),
            'php named .jpg' => $this->upload('logo.jpg', '<?php echo 1;'),
            'jpg named .php' => $this->upload('logo.php', $this->jpgBytes()),
            'jpg named .html' => $this->upload('logo.html', $this->jpgBytes()),
        ];
        $msg = 'Upload the logo as a '.Company::logoTypesLabel().' image.';
        foreach ($cases as $label => $file) {
            $this->save($this->base(['logo' => $file]))->assertSessionHasErrors(['logo' => $msg]);
            $this->assertNull(Company::current()->logo_path, $label);
        }
        $this->assertSame([], $this->companyFiles());
    }

    public function test_file_over_the_upload_limit_is_refused(): void
    {
        $big = $this->upload('big.jpg', $this->jpgBytes().str_repeat("\0", 1024 * 1024));
        $this->save($this->base(['logo' => $big]))->assertSessionHasErrors(['logo' => 'The file must not be larger than 1 MB.']);
        $this->save($this->base(['logo' => UploadedFile::fake()->create('big2.jpg', 2000, 'image/jpeg')]))->assertSessionHasErrors(['logo' => 'The file must not be larger than 1 MB.']);
        $this->assertNull(Company::current()->logo_path);
        $this->assertSame([], $this->companyFiles());
    }

    public function test_png_accepted_only_with_gd(): void
    {
        $gd = extension_loaded('gd');
        $png = $gd ? UploadedFile::fake()->image('logo.png', 400, 100) : $this->upload('logo.png', base64_decode(self::PNG));
        $res = $this->save($this->base(['logo' => $png]));

        if (! $gd) {
            $this->assertSame(['jpg', 'jpeg'], Company::logoTypes());
            $this->assertSame('JPG', Company::logoTypesLabel());
            $res->assertSessionHasErrors(['logo' => 'Upload the logo as a JPG image.']);
            $this->assertNull(Company::current()->logo_path);
            // a PNG renamed to .jpg is still a PNG
            $this->save($this->base(['logo' => $this->upload('logo.jpg', base64_decode(self::PNG))]))->assertSessionHasErrors('logo');
            $this->assertSame([], $this->companyFiles());

            return;
        }

        $res->assertSessionHasNoErrors();
        $c = Company::current();
        $this->assertStringEndsWith('.png', $c->logo_path);
        $logo = $c->pdfLogo();
        $this->assertStringStartsWith('data:image/png;base64,', $logo['src']);
        $this->assertEquals([200, 50], [$logo['width'], $logo['height']]);
    }

    public function test_user_without_masters_edit_cannot_save(): void
    {
        $role = Role::findOrCreate('Logo viewer', 'web');
        $role->givePermissionTo('masters.view');
        $u = User::create(['name' => 'Viewer', 'email' => 'viewer@company.example', 'mobile' => '9822000077', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        $this->actingAs($u)->get('/admin/masters/company')->assertOk();
        $this->save($this->base(['logo' => $this->jpg()]), $u)->assertForbidden();
        $this->save($this->base(['remove_logo' => '1']), $u)->assertForbidden();
        $this->assertNull(Company::query()->value('logo_path'));
        $this->assertSame([], $this->companyFiles());

        // an existing logo is not removed by them either
        $this->save($this->base(['logo' => $this->jpg()]))->assertSessionHasNoErrors();
        $path = Company::current()->logo_path;
        $this->save($this->base(['remove_logo' => '1']), $u)->assertForbidden();
        $this->assertSame($path, Company::current()->logo_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_emailed_quotation_pdf_has_the_logo(): void
    {
        $q = $this->quotation();
        $pdfOf = function () use ($q) {
            $att = (new QuotationMail($q->fresh(), null))->attachments()[0];

            return $att->attachWith(fn () => null, fn ($data) => $data());
        };
        $this->assertStringNotContainsString('/Subtype /Image', $pdfOf());

        $this->save($this->base(['logo' => $this->jpg()]))->assertSessionHasNoErrors();
        $pdf = $pdfOf();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertStringContainsString('/Width 300', $pdf);
    }
}
