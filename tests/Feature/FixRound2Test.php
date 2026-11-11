<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\RoleService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** One test (or more) per bug of the full test pass with 20 entries (B1 to B4). */
class FixRound2Test extends TestCase
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
        return User::where('email', 'priya@company.example')->first() ?? $this->makeUser(Roles::SALES, 'priya@company.example', '9822000010');
    }

    private function quotationPayload(array $over = []): array
    {
        return $this->newQuotationPayload($over);
    }

    private function salesRole(): Role
    {
        return Role::findByName(Roles::SALES, 'web');
    }

    // ---- B1: the Sales role keeps its name and cannot be deleted (decision U10) ----

    public function test_b1_sales_role_cannot_be_renamed_but_its_permissions_can_change(): void
    {
        $role = $this->salesRole();
        $perms = $role->permissions->pluck('name')->all();

        $this->actingAs($this->admin())->put("/admin/roles/{$role->id}", ['name' => 'Sales Team', 'permissions' => $perms])
            ->assertSessionHasErrors(['name' => 'The Sales role cannot be renamed, because quotations pick their sales person from it.']);
        $this->assertSame(Roles::SALES, $role->fresh()->name);

        // Same name with fewer permissions saves and is logged.
        $fewer = array_values(array_diff($perms, ['tax-invoice.delete']));
        $this->actingAs($this->admin())->put("/admin/roles/{$role->id}", ['name' => Roles::SALES, 'permissions' => $fewer])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/roles');
        $this->assertFalse($role->fresh()->hasPermissionTo('tax-invoice.delete'));
        $this->assertTrue(ActivityLog::where('action', 'role.permissions_updated')->where('subject_id', $role->id)->exists());
    }

    public function test_b1_service_refuses_renaming_admin_or_sales_from_any_caller(): void
    {
        foreach (Roles::FIXED as $name) {
            try {
                app(RoleService::class)->update(Role::findByName($name, 'web'), ['name' => $name.' 2', 'permissions' => []]);
                $this->fail("Renaming $name must be refused.");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('cannot be renamed', $e->getMessage());
            }
            $this->assertTrue(Role::where('name', $name)->exists());
        }
    }

    public function test_b1_sales_role_cannot_be_deleted_even_with_no_users(): void
    {
        $role = $this->salesRole();
        $this->assertSame(0, $role->users()->count());

        $this->actingAs($this->admin())->delete("/admin/roles/{$role->id}")
            ->assertSessionHasErrors(['delete' => 'The Sales role cannot be deleted, because quotations pick their sales person from it.']);
        $this->assertTrue(Role::where('name', Roles::SALES)->exists());
        $this->assertFalse(ActivityLog::where('action', 'role.deleted')->exists());
    }

    public function test_b1_sales_role_screen_has_read_only_name_and_no_delete_button(): void
    {
        $role = $this->salesRole();
        $page = $this->actingAs($this->admin())->get("/admin/roles/{$role->id}/edit")->assertOk()
            ->assertSee('Sales keeps its name and cannot be deleted')
            ->assertDontSee('Delete role')
            ->assertSee('Save role');
        $this->assertMatchesRegularExpression('/id="name"[^>]*readonly/', $page->getContent());

        // An ordinary role still has both.
        $other = Role::findByName('Purchase', 'web');
        $this->actingAs($this->admin())->get("/admin/roles/{$other->id}/edit")->assertOk()->assertSee('Delete role')
            ->assertDontSee('Sales keeps its name');
    }

    public function test_b1_quotations_keep_working_after_a_refused_sales_rename(): void
    {
        $sales = $this->sales();
        $role = $this->salesRole();
        $this->actingAs($this->admin())->put("/admin/roles/{$role->id}", ['name' => 'Sales Team', 'permissions' => $role->permissions->pluck('name')->all()]);

        $this->actingAs($sales)->post('/crm/quotations', $this->quotationPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, Quotation::count());
        $this->actingAs($sales)->getJson('/lookup/sales-people?q=Priya')->assertOk()->assertJsonPath('0.id', $sales->id);
    }

    // ---- B2: digits in a name or email search do not match mobiles ----

    public function test_b2_users_search_with_digits_in_an_email_matches_only_that_user(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser('Purchase', 'purchase2@test.local', '9811100001');
        // Mobiles containing a 2 that the old search also listed.
        $this->makeUser('Purchase', 'other1@test.local', '9822222222');
        $this->makeUser('Purchase', 'other3@test.local', '9812345678');

        foreach (['purchase2@', 'purchase2@test.local', 'Purchase2'] as $typed) {
            $this->actingAs($admin)->get('/admin/users?q='.urlencode($typed))
                ->assertViewHas('users', fn ($p) => $p->pluck('id')->all() === [$target->id]);
        }
    }

    public function test_b2_users_search_still_finds_mobiles_typed_as_a_phone_number(): void
    {
        $admin = $this->admin();
        $u = $this->makeUser('Purchase', 'mob@company.example', '9810000012');
        foreach (['+91 98100 00012', '098100 00012', '98100-00012', '(98100) 00012', '9810000012', '00012'] as $typed) {
            $this->actingAs($admin)->get('/admin/users?q='.urlencode($typed))
                ->assertViewHas('users', fn ($p) => $p->pluck('id')->all() === [$u->id]);
        }
    }

    // ---- B3: % and _ in the Prospect and Quotation entry search are matched literally ----

    public function test_b3_prospect_and_quotation_entry_search_treat_wildcards_literally(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $this->assertSame(1, Prospect::count());
        $this->assertSame(1, Quotation::count());

        foreach (['%', '_'] as $typed) {
            $this->actingAs($admin)->getJson('/crm/prospects/lookup?q='.urlencode($typed))->assertOk()->assertExactJson([]);
            $this->actingAs($admin)->getJson('/crm/quotations/lookup?q='.urlencode($typed))->assertOk()->assertExactJson([]);
        }
        // Ordinary text still matches.
        $this->actingAs($admin)->getJson('/crm/prospects/lookup?q=Kaveri')->assertOk()->assertJsonCount(1);
        $this->actingAs($admin)->getJson('/crm/quotations/lookup?q=Kaveri')->assertOk()->assertJsonCount(1);
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q=%25')->assertOk()->assertExactJson([]);
        $this->actingAs($admin)->getJson('/crm/enquiries/lookup?q=Kaveri')->assertOk()->assertJsonCount(1);
    }

    // ---- B4: an earlier, read-only revision has no Delete and cannot be deleted ----

    public function test_b4_earlier_revision_shows_no_delete_and_delete_from_it_is_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $rev0 = Quotation::first();
        $this->actingAs($admin)->post("/crm/quotations/{$rev0->id}/revise")->assertSessionHasNoErrors();
        $rev1 = Quotation::where('is_latest', true)->first();

        $this->actingAs($admin)->get("/crm/quotations/{$rev0->id}")->assertOk()
            ->assertSee('is an earlier revision and is read-only')->assertDontSee('Delete quotation');
        // Thu 22/10/2026: no Delete quotation button on any revision now (the destroy route stays, as below).
        $this->actingAs($admin)->get("/crm/quotations/{$rev1->id}")->assertOk()->assertSee('Save')->assertDontSee('Delete quotation')->assertDontSee('delForm');

        $this->actingAs($admin)->delete("/crm/quotations/{$rev0->id}")->assertSessionHasErrors('delete');
        $this->assertSame(2, Quotation::count());
        $this->assertFalse(ActivityLog::where('action', 'quotation.deleted')->exists());

        // From the current revision Delete removes every revision, as its confirm says.
        $this->actingAs($admin)->delete("/crm/quotations/{$rev1->id}")->assertSessionHasNoErrors()->assertRedirect('/crm/quotations');
        $this->assertSame(0, Quotation::count());
        $this->assertTrue(ActivityLog::where('action', 'quotation.deleted')->exists());
    }

    public function test_b4_stale_delete_of_a_revision_revised_meanwhile_is_refused_inside_the_lock(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/crm/quotations', $this->quotationPayload());
        $staleCopy = Quotation::first();                                   // user A opened Rev 0
        app(QuotationService::class)->revise(Quotation::first(), $admin);  // user B revised it

        try {
            app(QuotationService::class)->delete($staleCopy, $admin);
            $this->fail('Deleting from a revision that is no longer current must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('earlier revision', $e->getMessage());
        }
        $this->assertSame(2, Quotation::count());
    }
}
