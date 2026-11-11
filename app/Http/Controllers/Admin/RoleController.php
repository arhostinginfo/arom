<?php

namespace App\Http\Controllers\Admin;

use App\Support\Roles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\ActivityLog;
use App\Services\RoleService;
use App\Support\Access;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

// Admin > Access rights: roles and what each role may do (decisions D2, D19, U4, U5).
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles)
    {
    }

    public function index(Request $request): View
    {
        // Paged in SQL like every list (rule P1). Admin first, then by name.
        $roles = Role::withCount(['users', 'permissions'])->orderByRaw('name = ? desc', [Roles::ADMIN])->orderBy('name')->orderBy('id')
            ->paginate(ListQuery::perPage($request))->withQueryString();

        return view('admin.roles.index', [
            'roles' => $roles,
            'total' => count(Access::all()),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Role());
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->validated());

        return redirect()->route('admin.roles.index')->with('toast', 'Role '.$role->name.' created');
    }

    public function edit(Role $role): View
    {
        return $this->form($role);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated());

        return redirect()->route('admin.roles.index')->with('toast', 'Role '.$role->name.' saved');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->roles->delete($role);

        return redirect()->route('admin.roles.index')->with('toast', 'Role '.$role->name.' deleted');
    }

    private function form(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
            'locked' => $role->name === Roles::ADMIN,
            // Decision U10: Admin and Sales cannot be renamed or deleted.
            'fixedName' => $role->exists && Roles::isFixed($role->name),
            'userCount' => $role->exists ? $role->users()->count() : 0,
            'history' => ActivityLog::history($role, 8),
        ]);
    }
}
