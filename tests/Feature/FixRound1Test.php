<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FinancialYear;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\NumberSeries;
use App\Services\QuotationService;
use App\Support\Csv;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** One test (or more) per must-fix item of fix round 1. Ids refer to the review files and the test report. */
class FixRound1Test extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function makeUser(string $role, string $email, string $mobile, string $status = 'Active'): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => $mobile, 'password' => 'Secret2026', 'status' => $status]);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** A non-Admin office manager who may view and edit users and roles, and see the CRM reports. */
    private function manager(): User
    {
        $role = Role::findOrCreate('Office', 'web');
        $role->syncPermissions(['users.view', 'users.add', 'users.edit', 'users.delete', 'access-rights.view', 'access-rights.edit', 'reports-crm.view']);

        return $this->makeUser('Office', 'manager@company.example', '9822000030');
    }

    /** A user whose rights the manager holds, so the manager may change them (decision U9). */
    private function reportsUser(): User
    {
        Role::findOrCreate('Reports', 'web')->syncPermissions(['reports-crm.view']);

        return $this->makeUser('Reports', 'rahul@company.example', '9822000002');
    }

    private function userPayload(User $u, array $over = []): array
    {
        return array_merge([
            'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile,
            'password' => '', 'password_confirmation' => '',
            'role' => $u->roleName(), 'units' => [Unit::first()->id], 'status' => 'Active', 'permissions' => [],
        ], $over);
    }

    private function sales(): User
    {
        return User::where('email', 'priya@company.example')->first() ?? $this->makeUser('Sales', 'priya@company.example', '9822000010');
    }

    private function quotationPayload(array $over = []): array
    {
        return $this->newQuotationPayload($over);
    }

    // ---- Code review M1 / test report B1: Admin takeover through Users Edit (decision U8) ----

    public function test_m1_non_admin_cannot_make_themself_or_anyone_admin(): void
    {
        $m = $this->manager();
        $this->actingAs($m)->put("/admin/users/{$m->id}", $this->userPayload($m, ['role' => 'Admin']))->assertSessionHasErrors('role');
        $this->assertFalse($m->fresh()->hasRole('Admin'));

        $other = $this->reportsUser();
        $this->actingAs($m)->put("/admin/users/{$other->id}", $this->userPayload($other, ['role' => 'Admin']))->assertSessionHasErrors('role');
        $this->assertTrue($other->fresh()->hasRole('Reports'));

        $this->actingAs($m)->post('/admin/users', $this->userPayload(new User(['name' => 'New One', 'email' => 'new@company.example', 'mobile' => '9822000031']), [
            'role' => 'Admin', 'password' => 'Secret2026', 'password_confirmation' => 'Secret2026',
        ]))->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new@company.example']);
    }

    public function test_m1_non_admin_cannot_change_own_role_or_give_permissions_they_do_not_hold(): void
    {
        $m = $this->manager();
        $this->actingAs($m)->put("/admin/users/{$m->id}", $this->userPayload($m, ['role' => 'Sales']))->assertSessionHasErrors('role');

        $other = $this->reportsUser();
        $this->actingAs($m)->put("/admin/users/{$other->id}", $this->userPayload($other, ['permissions' => ['access-rights.add']]))
            ->assertSessionHasErrors('permissions');
        $this->assertFalse($other->fresh()->can('access-rights.add'));

        // a permission the manager holds can be given
        $this->actingAs($m)->put("/admin/users/{$other->id}", $this->userPayload($other, ['permissions' => ['users.view']]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->can('users.view'));
    }

    public function test_m1_non_admin_cannot_change_reset_password_of_or_delete_an_admin(): void
    {
        $m = $this->manager();
        $admin = $this->admin();
        $this->actingAs($m)->put("/admin/users/{$admin->id}", $this->userPayload($admin, [
            'role' => 'Admin', 'password' => 'Taken2026x', 'password_confirmation' => 'Taken2026x',
        ]))->assertForbidden();
        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Taken2026x'])->assertSessionHasErrors('email');

        $this->actingAs($m)->delete("/admin/users/{$admin->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_m1_non_admin_cannot_add_permissions_they_do_not_hold_to_a_role(): void
    {
        $m = $this->manager();
        $role = Role::findByName('Purchase');
        $this->actingAs($m)->put("/admin/roles/{$role->id}", ['name' => 'Purchase', 'permissions' => array_merge($role->permissions->pluck('name')->all(), ['access-rights.delete'])])
            ->assertSessionHasErrors('permissions');
        $this->assertFalse($role->fresh()->hasPermissionTo('access-rights.delete'));
    }

    // ---- Code review M2 / test report B4: CSV formula injection ----

    public function test_m2_csv_download_neutralises_formulas_and_keeps_numbers(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\",\"click\")", Csv::safe('=HYPERLINK("http://x","click")'));
        $this->assertSame("'@SUM(A1)", Csv::safe('@SUM(A1)'));
        $this->assertSame("'\tx", Csv::safe("\tx"));
        $this->assertSame("'-cmd", Csv::safe('-cmd'));
        $this->assertSame('+91 98220 00001', Csv::safe('+91 98220 00001'));
        $this->assertSame('-12.5', Csv::safe('-12.5'));
        $this->assertSame('Orion', Csv::safe('Orion'));
        $this->assertSame('=x', Csv::unsafe("'=x"));
        $this->assertSame("'quoted", Csv::unsafe("'quoted"));

        $admin = $this->admin();
        Prospect::create(['name' => '=HYPERLINK("http://x","click")', 'market' => 'Domestic', 'phone_1' => '+91 98220 11111']);
        $csv = $this->actingAs($admin)->get('/crm/prospects/export')->streamedContent();
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://x\"\",\"\"click\"\")\"", $csv);
        $this->assertStringContainsString('+91 98220 11111', $csv);

        // the downloaded file imports back with the original name
        Prospect::query()->forceDelete();
        $this->actingAs($admin)->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertRedirect('/crm/prospects');
        $this->assertSame('=HYPERLINK("http://x","click")', Prospect::first()->name);
    }

    // ---- Code review M3: Windows-1252 CSV import ----

    public function test_m3_windows_1252_csv_imports_without_a_server_error(): void
    {
        $csv = "Prospect name,Domestic / Export,Contact no 1\n".mb_convert_encoding('Café Agro', 'Windows-1252', 'UTF-8').",Domestic,9822012345\n";
        $this->assertFalse(mb_check_encoding($csv, 'UTF-8'));
        $this->actingAs($this->admin())->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
            ->assertRedirect('/crm/prospects')->assertSessionHasNoErrors();
        $this->assertSame('Café Agro', Prospect::first()->name);
    }

    // ---- Test report B2: inactive message only after the right password ----

    public function test_b2_wrong_password_does_not_reveal_an_inactive_account(): void
    {
        $this->makeUser('Purchase', 'rahul@company.example', '9822000002', 'Inactive');
        $this->post('/login', ['email' => 'rahul@company.example', 'password' => 'wrong-one'])
            ->assertSessionHasErrors(['email' => 'The login email or password is wrong.']);
        $this->post('/login', ['email' => 'rahul@company.example', 'password' => 'Secret2026'])
            ->assertSessionHasErrors(['email' => 'This account is inactive. Contact your administrator.']);
        $this->assertGuest();
    }

    // ---- Test report B3: an inactive Admin can be deleted ----

    public function test_b3_inactive_admin_can_be_deleted_but_the_last_active_admin_cannot(): void
    {
        $admin = $this->admin();
        $inactive = $this->makeUser('Admin', 'old.admin@company.example', '9822000040', 'Inactive');
        $this->actingAs($admin)->delete("/admin/users/{$inactive->id}")->assertRedirect('/admin/users');
        $this->assertSoftDeleted('users', ['id' => $inactive->id]);

        // the only active Admin stays: the service refuses even when called directly
        $other = $this->makeUser('Admin', 'second@company.example', '9822000041');
        $this->actingAs($other)->delete("/admin/users/{$admin->id}")->assertRedirect('/admin/users');
        $this->expectException(ValidationException::class);
        app(\App\Services\UserService::class)->delete($other->fresh(), $this->makeUser('Admin', 'third@company.example', '9822000042', 'Inactive'));
    }

    // ---- DB-M1 / A3: number series lock first, imports reserve a block ----

    public function test_db_m1_number_series_reserves_consecutive_numbers_and_creates_a_missing_series(): void
    {
        $unit = Unit::first();
        $year = FinancialYear::forDate(now());
        $series = app(NumberSeries::class);
        $fy = $year->code;

        $this->assertSame(["QT/$fy/0001"], DB::transaction(fn () => [$series->next('quotation', $unit, $year)]));
        $this->assertSame(["QT/$fy/0002", "QT/$fy/0003", "QT/$fy/0004"], DB::transaction(fn () => $series->reserve('quotation', $unit, $year, 3)));
        $this->assertSame(5, DB::table('number_series')->where(['document_type' => 'quotation', 'unit_id' => $unit->id])->value('next_number'));
        $this->assertSame(1, DB::table('number_series')->where(['document_type' => 'quotation', 'unit_id' => $unit->id])->count());
    }

    public function test_a3_import_takes_its_numbers_in_one_block_and_later_saves_continue_after_it(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();
        $no = $this->crmEnquiry()->enquiry_no;
        $csv = "Group,Enquiry no,Quotation date,Sales person login email,Item description,Qty,Rate\n"
            ."A,$no,01/10/2026,{$sales->email},Gear,1,1\nB,$no,01/10/2026,{$sales->email},Shaft,1,1\nC,$no,01/10/2026,{$sales->email},Hub,1,1\n";
        $this->actingAs($admin)->post('/crm/quotations/import', ['file' => UploadedFile::fake()->createWithContent('q.csv', $csv)])->assertRedirect('/crm/quotations');
        $fy = FinancialYear::forDate(now())->code;
        $this->assertSame(["QT/$fy/0001", "QT/$fy/0002", "QT/$fy/0003"], Quotation::orderBy('id')->pluck('quotation_no')->all());

        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $this->assertSame("QT/$fy/0004", Quotation::orderByDesc('id')->value('quotation_no'));
    }

    // ---- DB-M5 / A7: saving a revision that someone else has just revised is refused ----

    public function test_db_m5_stale_save_of_a_revised_quotation_is_refused_inside_the_lock(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $staleCopy = Quotation::first();               // user A opened Rev 0
        app(QuotationService::class)->revise(Quotation::first(), $admin); // user B revised it

        $this->assertTrue($staleCopy->is_latest);      // A's copy still says "current"
        try {
            app(QuotationService::class)->update($staleCopy, $this->quotationPayload(['site' => 'Changed by A']), $admin);
            $this->fail('A stale save must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('earlier revision', $e->getMessage());
        }
        $this->assertSame('Tower B lobby', $staleCopy->fresh()->site);
    }

    public function test_a7_enquiry_cannot_be_saved_for_a_prospect_deleted_meanwhile(): void
    {
        $admin = $this->admin();
        $p = Prospect::create(['name' => 'Gone Ltd', 'market' => 'Domestic', 'phone_1' => '9822012399']);
        $data = $this->enquiryPayload(['prospect_id' => $p->id]);
        $p->delete();
        $this->expectException(ValidationException::class);
        app(\App\Services\EnquiryService::class)->create($data, $admin, Unit::first());
    }

    public function test_a7_quotation_cannot_be_saved_for_an_enquiry_deleted_meanwhile(): void
    {
        $admin = $this->admin();
        $data = $this->quotationPayload();
        \App\Models\Enquiry::first()->delete();
        $this->expectException(ValidationException::class);
        app(QuotationService::class)->create($data, $admin, Unit::first());
    }

    public function test_c11_quotation_takes_a_customer_or_a_prospect_not_both(): void
    {
        $party = Party::create(['name' => 'Apex Auto Components', 'type' => 'Customer', 'status' => 'Active']);
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['party_id' => $party->id]))->assertSessionHasErrors('prospect_id');
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['party_id' => '', 'prospect_id' => '']))->assertSessionHasErrors('prospect_id');
        $this->assertSame(0, \App\Models\Enquiry::count());
    }

    // ---- A4 / DB-M6: report PDF limit and download rate limit ----

    public function test_a4_report_pdf_is_limited_and_csv_still_works(): void
    {
        $admin = $this->admin();
        $now = now();
        $rows = [];
        // The PDF cap scales with the columns (ReportPdf, 27/10/2026): the Prospects report has 13.
        $cap = \App\Support\ReportPdf::maxRows(13);
        for ($i = 1; $i <= $cap + 1; $i++) {
            $rows[] = ['name' => 'P'.$i, 'market' => 'Domestic', 'phone_1' => '98220'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'unit_id' => Unit::first()->id, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('prospects')->insert($chunk);
        }
        $year = FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];

        $this->actingAs($admin)->get('/reports/crm/prospects/download?'.http_build_query($range + ['format' => 'pdf']))
            ->assertSessionHasErrors(['format' => \App\Support\ReportPdf::tooMany($cap)]);
        $csv = $this->actingAs($admin)->get('/reports/crm/prospects/download?'.http_build_query($range + ['format' => 'csv']))->streamedContent();
        $this->assertSame($cap + 2, substr_count(trim($csv), "\n") + 1);
    }

    public function test_a4_downloads_are_limited_to_ten_a_minute_per_user(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($admin)->get('/crm/prospects/import-template')->assertOk();
        }
        $this->actingAs($admin)->get('/crm/prospects/import-template')->assertStatus(429);
        // another user has their own allowance
        $this->actingAs($this->sales())->get('/crm/prospects/import-template')->assertOk();
    }

    // ---- DB-M7 / decision C13: CSV downloads stream by id ----

    public function test_db_m7_csv_download_is_in_the_order_rows_were_added(): void
    {
        Prospect::create(['name' => 'Zeta Tools', 'market' => 'Domestic', 'phone_1' => '9822012301']);
        Prospect::create(['name' => 'Alpha Gears', 'market' => 'Domestic', 'phone_1' => '9822012302']);
        $csv = $this->actingAs($this->admin())->get('/crm/prospects/export')->streamedContent();
        $this->assertLessThan(strpos($csv, 'Alpha Gears'), strpos($csv, 'Zeta Tools'));
    }

    // ---- A5 / DB-M2: type-ahead pickers instead of whole-table dropdowns ----

    public function test_a5_lookups_return_at_most_twenty_starts_with_matches_and_need_permission(): void
    {
        $now = now();
        DB::table('prospects')->insert(array_map(fn ($i) => ['name' => 'Kaveri '.$i, 'market' => 'Domestic', 'phone_1' => '98220'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'created_at' => $now, 'updated_at' => $now], range(1, 25)));
        Prospect::create(['name' => 'Shree Kaveri', 'market' => 'Export', 'phone_1' => '9822099999']);

        $res = $this->actingAs($this->admin())->getJson('/lookup/prospects?q=Kaveri')->assertOk();
        $this->assertCount(20, $res->json());
        $this->assertNotContains('Shree Kaveri', array_column($res->json(), 'label'));
        $this->assertSame(['id', 'label', 'sub'], array_keys($res->json()[0]));

        // LIKE wildcards typed by the user are taken literally
        $this->assertCount(0, $this->actingAs($this->admin())->getJson('/lookup/prospects?q=%25')->json());

        $buyer = $this->makeUser('Purchase', 'buyer@company.example', '9822000020');
        $this->actingAs($buyer)->getJson('/lookup/prospects?q=K')->assertForbidden();
        $this->actingAs($buyer)->getJson('/lookup/customers?q=K')->assertForbidden();
        $this->actingAs($buyer)->getJson('/lookup/users?q=A')->assertForbidden();
        $this->actingAs($this->sales())->getJson('/lookup/sales-people?q=P')->assertOk()->assertJsonPath('0.label', 'Priya');
    }

    public function test_a5_quotation_page_does_not_print_every_prospect_when_there_are_many(): void
    {
        $now = now();
        DB::table('prospects')->insert(array_map(fn ($i) => ['name' => 'Bulk '.$i, 'market' => 'Domestic', 'phone_1' => '98220'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'created_at' => $now, 'updated_at' => $now], range(1, 205)));
        $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertOk()
            ->assertViewHas('prospects', fn ($list) => $list->isEmpty())
            ->assertDontSee('Bulk 150');
    }

    // ---- A6: financial year created race-safe, remembered per request, next year made ahead ----

    public function test_a6_financial_years_are_created_ahead_and_only_one_is_current(): void
    {
        $this->assertNotNull(FinancialYear::where('code', FinancialYear::codeFor(FinancialYear::startYearFor(now()) + 1))->first());

        FinancialYear::query()->update(['is_current' => true]);
        $this->artisan('nursery:financial-years')->assertSuccessful();
        $this->assertSame(1, FinancialYear::where('is_current', true)->count());
        $this->assertSame(FinancialYear::forDate(now())->id, FinancialYear::where('is_current', true)->value('id'));

        DB::enableQueryLog();
        FinancialYear::forDate(now());
        FinancialYear::forDate(now());
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();

        // a missing year is created once; a second call finds it instead of failing on the unique code
        $a = FinancialYear::forDate(now()->addYears(5));
        app()->forgetScopedInstances();
        $b = FinancialYear::forDate(now()->addYears(5));
        $this->assertSame($a->id, $b->id);
    }

    // ---- DB-M3 and rule P1: server-side paging, page size and sort in the URL ----

    public function test_p1_activity_log_pages_without_counting_and_keeps_filters_in_the_links(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 30; $i++) {
            \App\Support\ActivityLogger::log('user.updated', $admin, 'Update '.$i);
        }
        $this->actingAs($admin)->get('/admin/activity-log?action=user.updated')->assertOk()
            ->assertViewHas('logs', fn ($p) => $p instanceof Paginator && ! $p instanceof LengthAwarePaginator && $p->count() === 25 && $p->hasMorePages())
            ->assertSee('action=user.updated', false);
        $this->actingAs($admin)->get('/admin/activity-log?per_page=100')->assertViewHas('logs', fn ($p) => $p->perPage() === 100);
        $this->actingAs($admin)->get('/admin/activity-log?per_page=5000')->assertViewHas('logs', fn ($p) => $p->perPage() === 25);
    }

    public function test_p1_users_and_roles_lists_page_and_sort_on_the_server(): void
    {
        $admin = $this->admin();
        foreach (range(1, 27) as $i) {
            $this->makeUser('Purchase', "u$i@company.example", '98221'.str_pad((string) $i, 5, '0', STR_PAD_LEFT));
        }
        $this->actingAs($admin)->get('/admin/users?sort=name&dir=desc')->assertOk()
            ->assertViewHas('users', fn ($p) => $p instanceof LengthAwarePaginator && $p->count() === 25 && $p->total() === 28)
            ->assertSee('sort=name', false);
        $this->actingAs($admin)->get('/admin/users?per_page=50')->assertViewHas('users', fn ($p) => $p->count() === 28);
        // an unknown sort column falls back to the default instead of reaching SQL
        $this->actingAs($admin)->get('/admin/users?sort=password')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertViewHas('roles', fn ($p) => $p instanceof LengthAwarePaginator);
    }

    // ---- A2: trusted proxies from config ----

    public function test_a2_client_ip_comes_from_a_trusted_proxy_header(): void
    {
        try {
            config(['app.trusted_proxies' => ['*']]);
            (new AppServiceProvider(app()))->boot();
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->withHeader('X-Forwarded-For', '203.0.113.9')
                ->post('/login', ['email' => 'admin@nursery.local', 'password' => 'Admin@12345']);
            $this->assertSame('203.0.113.9', ActivityLog::where('action', 'auth.login')->latest('id')->value('ip_address'));
        } finally {
            TrustProxies::flushState();
        }
    }

    // ---- UX M3, M8, M9: unsaved-changes guard, Save blocks a second click, skip link ----

    public function test_ux_forms_guard_unsaved_changes_and_double_saves_and_layout_has_a_skip_link(): void
    {
        $admin = $this->admin();
        foreach (['/admin/users/create', '/admin/roles/create', '/crm/prospects/create', '/crm/quotations/create', '/crm/enquiries/create', '/crm/site-visits/create', '/crm/pos/create', '/admin/masters/employees/create', '/admin/approval'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-dirty-guard', false)->assertSee('data-saving', false)
                ->assertSee('Skip to content');
        }
        // one "Company / Client name *" group on the enquiry form (UX M6); a quotation takes its client from the enquiry
        $this->actingAs($admin)->get('/crm/enquiries/create')->assertSee('Company / Client name');
    }

    // ---- User module test (09/10/2026), must-fix B1 and B2: decision U9 ----

    public function test_u9_non_admin_cannot_give_a_role_with_rights_they_do_not_hold(): void
    {
        $m = $this->manager();
        $new = fn (string $role, string $email, string $mobile) => $this->userPayload(new User(['name' => 'New One', 'email' => $email, 'mobile' => $mobile]), [
            'role' => $role, 'password' => 'Secret2026', 'password_confirmation' => 'Secret2026',
        ]);
        $this->actingAs($m)->post('/admin/users', $new('Sales', 'new@company.example', '9822000031'))->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new@company.example']);

        Role::findOrCreate('Viewer Only', 'web')->syncPermissions(['users.view', 'activity-log.view']);
        $this->actingAs($m)->post('/admin/users', $new('Viewer Only', 'viewer@company.example', '9822000032'))->assertSessionHasErrors('role');

        // a role inside the manager's own rights can be given
        Role::findOrCreate('Reports', 'web')->syncPermissions(['reports-crm.view']);
        $this->actingAs($m)->post('/admin/users', $new('Reports', 'ok@company.example', '9822000033'))->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'ok@company.example')->first()->hasRole('Reports'));

        // the Role list on the form offers only those roles
        $this->actingAs($m)->get('/admin/users/create')->assertOk()
            ->assertSee('<option value="Reports"', false)->assertDontSee('<option value="Sales"', false)->assertDontSee('<option value="Admin"', false);
    }

    public function test_u9_non_admin_cannot_change_reset_or_delete_a_user_with_more_rights(): void
    {
        $m = $this->manager();
        $accounts = $this->makeUser('Accounts', 'accounts@company.example', '9822000050');

        $this->actingAs($m)->put("/admin/users/{$accounts->id}", $this->userPayload($accounts, [
            'password' => 'Taken2026x', 'password_confirmation' => 'Taken2026x',
        ]))->assertForbidden();
        auth()->logout();
        $this->post('/login', ['email' => $accounts->email, 'password' => 'Taken2026x'])->assertSessionHasErrors('email');

        $this->actingAs($m)->put("/admin/users/{$accounts->id}", $this->userPayload($accounts, ['status' => 'Inactive']))->assertForbidden();
        $this->assertTrue($accounts->fresh()->isActive());

        $this->actingAs($m)->delete("/admin/users/{$accounts->id}")->assertSessionHasErrors(['delete' => 'You can only change or delete users whose rights you have yourself.']);
        $this->assertNotSoftDeleted('users', ['id' => $accounts->id]);

        // the edit page is read-only and offers no Delete
        $this->actingAs($m)->get("/admin/users/{$accounts->id}/edit")->assertOk()
            ->assertSee('This user has rights you do not have')->assertViewHas('manageLocked', true);

        // a user inside the manager's rights can still be changed and deleted
        $r = $this->reportsUser();
        $this->actingAs($m)->put("/admin/users/{$r->id}", $this->userPayload($r, ['password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026']))
            ->assertRedirect('/admin/users');
        $this->actingAs($m)->delete("/admin/users/{$r->id}")->assertRedirect('/admin/users');
        $this->assertSoftDeleted('users', ['id' => $r->id]);
    }

    // ---- User module test suggestions B3 to B6 ----

    public function test_users_search_finds_a_mobile_typed_with_91_and_treats_wildcards_literally(): void
    {
        $admin = $this->admin();
        $u = $this->makeUser('Purchase', 'mob@company.example', '9810000012');
        foreach (['+91 98100 00012', '098100 00012', '98100 00012'] as $typed) {
            $this->actingAs($admin)->get('/admin/users?q='.urlencode($typed))
                ->assertViewHas('users', fn ($p) => $p->pluck('id')->all() === [$u->id]);
        }
        foreach (['%', '_'] as $typed) {
            $this->actingAs($admin)->get('/admin/users?q='.urlencode($typed))->assertViewHas('users', fn ($p) => $p->total() === 0);
        }
    }

    public function test_activity_log_shows_an_error_for_a_wrong_date_instead_of_guessing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/activity-log?from=31/02/2026&to=abc')->assertOk()
            ->assertSee('Enter From date as a real date, dd/mm/yyyy.')
            ->assertSee('Enter To date as a real date, dd/mm/yyyy.')
            ->assertSee('aria-invalid="true"', false);
        $this->actingAs($admin)->get('/admin/activity-log?from=10/10/2026&to=01/10/2026')->assertOk()
            ->assertSee('To date must be on or after From date.');
        $this->actingAs($admin)->get('/admin/activity-log?from=01/10/2026&to=31/10/2026')->assertOk()
            ->assertDontSee('as a real date');
    }

    public function test_users_list_has_a_sort_control_for_phone_width(): void
    {
        $this->actingAs($this->admin())->get('/admin/users?sort=last_login&dir=desc')->assertOk()
            ->assertSee('<label for="sort">Sort by</label>', false)
            ->assertSee('<option value="last_login" selected', false)
            ->assertSee('<option value="desc" selected', false);
    }

    // ---- Sat 09/10/2026: no Decision labels on any screen; the choices stay built in ----

    public function test_no_screen_shows_a_decision_label(): void
    {
        $admin = $this->admin();
        $pages = ['/admin/users', '/admin/users/create', "/admin/users/{$admin->id}/edit", '/admin/roles', '/admin/roles/create',
            '/admin/activity-log', '/crm/prospects/create', '/crm/quotations/create', '/reports/crm/prospects', '/reports/crm/enquiries',
            '/crm/enquiries/create', '/crm/enquiries/list', '/crm/site-visits/create', '/crm/site-visits/list', '/crm/pos/create', '/crm/quotations/list', '/dashboard',
            '/admin/masters', '/admin/masters/sources', '/admin/masters/employees', '/admin/masters/employees/create', '/admin/page-setup', '/admin/page-setup/enquiry',
            '/admin/approval', '/reports/crm/lead-ageing', '/reports/crm/lead-report'];
        foreach (['dashboard', 'sales', 'grn', 'po', 'transfer', 'entry', 'ledger', 'masters', 'config', 'approval', 'pagesetup', 'dashcfg', 'reports'] as $p) {
            $pages[] = '/app/'.$p;
        }
        foreach ($pages as $url) {
            $html = preg_replace('/<!--.*?-->/s', '', $this->actingAs($admin)->followingRedirects()->get($url)->assertOk()->getContent());
            $this->assertDoesNotMatchRegularExpression('/decision/i', $html, $url.' shows Decision wording');
            $this->assertStringNotContainsString('class="tg d"', $html, $url);
        }
        $js = file_get_contents(public_path('js/preview.js'));
        $this->assertStringNotContainsString('DEC(', $js);
        $this->assertDoesNotMatchRegularExpression("/'[^'\\n]*Decision[^'\\n]*'/", $js);
        $this->assertStringNotContainsString('.tg.d{', file_get_contents(public_path('css/nursery.css')));
    }
}
