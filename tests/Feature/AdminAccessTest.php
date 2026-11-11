<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function userPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Rahul Kulkarni',
            'email' => 'rahul@company.example',
            'mobile' => '+91 98220 00002',
            'password' => 'Purchase2026',
            'password_confirmation' => 'Purchase2026',
            'role' => 'Purchase',
            'units' => [Unit::first()->id],
            'status' => 'Active',
            'permissions' => [],
        ], $over);
    }

    public function test_login_is_logged_and_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['email' => 'admin@nursery.local', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.failed']);

        $this->post('/login', ['email' => 'admin@nursery.local', 'password' => 'Admin@12345'])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $this->admin()->id]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->userPayload(['status' => 'Inactive']));
        auth()->logout();
        $this->post('/login', ['email' => 'rahul@company.example', 'password' => 'Purchase2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_required_user_fields(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [])
            ->assertSessionHasErrors(['name', 'email', 'mobile', 'password', 'role', 'units', 'status']);
        $this->actingAs($this->admin())->post('/admin/users', $this->userPayload(['mobile' => '12345']))
            ->assertSessionHasErrors('mobile');
    }

    public function test_admin_creates_user_with_role_and_extra_permission_and_each_step_is_logged(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->userPayload(['permissions' => ['party-ledger.view', 'grn.add']]))
            ->assertRedirect('/admin/users');

        $u = User::where('email', 'rahul@company.example')->first();
        $this->assertSame('9822000002', $u->mobile);
        $this->assertTrue($u->hasRole('Purchase'));
        // grn.add comes from the role, so only the ledger is stored on the user
        $this->assertSame(['party-ledger.view'], $u->getDirectPermissions()->pluck('name')->all());
        $this->assertTrue($u->can('party-ledger.view'));
        $this->assertFalse($u->can('quotation.view'));

        foreach (['user.created', 'user.role_assigned', 'user.permissions_updated'] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action, 'subject_id' => $u->id, 'user_id' => $this->admin()->id]);
        }
    }

    public function test_role_create_change_and_delete_are_logged_and_enforced(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/roles', ['name' => 'Quality', 'permissions' => ['grn.edit']])->assertRedirect('/admin/roles');
        $role = Role::findByName('Quality');
        $this->assertEqualsCanonicalizing(['grn.edit', 'grn.view'], $role->permissions->pluck('name')->all());

        $this->actingAs($admin)->post('/admin/users', $this->userPayload(['role' => 'Quality']));
        $u = User::where('email', 'rahul@company.example')->first();

        // GRN entry is built: the old /app/grn address redirects (as /app/po does) to the page, which the role can open.
        $this->actingAs($u)->get('/app/grn')->assertRedirect(route('purchase.grns.index'));
        $this->actingAs($u)->get(route('purchase.grns.index'))->assertOk();
        $this->actingAs($u)->get('/app/po')->assertForbidden();
        $this->actingAs($u)->get('/admin/users')->assertForbidden();

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", ['name' => 'Quality', 'permissions' => ['grn.view', 'purchase-order.view']]);
        $this->assertTrue($u->fresh()->can('purchase-order.view'));
        $log = ActivityLog::where('action', 'role.permissions_updated')->latest('id')->first();
        $this->assertSame(['Purchase order · View'], $log->changes['added']);
        $this->assertSame(['GRN entry · Edit'], $log->changes['removed']);

        // a role with users cannot be deleted
        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->put("/admin/users/{$u->id}", $this->userPayload(['role' => 'Sales', 'password' => '', 'password_confirmation' => '']));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.role_assigned', 'description' => 'Changed role of Rahul Kulkarni from Quality to Sales']);
        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.deleted', 'subject_label' => 'Quality']);
    }

    public function test_admin_role_and_last_admin_are_protected(): void
    {
        $admin = $this->admin();
        $adminRole = Role::findByName('Admin');
        $this->actingAs($admin)->delete("/admin/roles/{$adminRole->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", $this->userPayload([
            'name' => $admin->name, 'email' => $admin->email, 'mobile' => $admin->mobile, 'role' => 'Sales', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_user_without_rights_cannot_manage_users(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->userPayload());
        $u = User::where('email', 'rahul@company.example')->first();
        $this->actingAs($u)->post('/admin/users', $this->userPayload(['email' => 'x@y.example', 'mobile' => '9822000099']))->assertForbidden();
        $this->actingAs($u)->post('/admin/roles', ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($u)->get('/admin/activity-log')->assertForbidden();
    }

    public function test_deleting_a_user_keeps_the_log(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->userPayload());
        $u = User::where('email', 'rahul@company.example')->first();
        $this->actingAs($this->admin())->delete("/admin/users/{$u->id}")->assertRedirect('/admin/users');
        $this->assertSoftDeleted('users', ['id' => $u->id]);
        auth()->logout();
        $this->post('/login', ['email' => 'rahul@company.example', 'password' => 'Purchase2026'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted', 'subject_id' => $u->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created', 'subject_id' => $u->id]);
    }
}
