<?php

namespace App\Http\Controllers\Admin;

use App\Support\Roles;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\UserService;
use App\Support\Access;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

// Admin > Users list (workbook ADMIN tab, decisions D19 and U1 to U3).
class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        // A mobile typed as +91 98100 00012 or 098100 00012 is searched as its 10 stored digits.
        // Only text made of digits, spaces, +, - or brackets is a mobile, so "purchase2@" stays a name/email search.
        $digits = preg_match('/^[\d\s+\-()]+$/', $q) ? UserRequest::normaliseMobile($q) : '';
        // Typed % and _ are matched literally, not as LIKE wildcards.
        $like = '%'.addcslashes($q, '%_\\').'%';

        $query = User::query()
            ->with(['roles', 'units', 'permissions'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->when($digits !== '', fn ($m) => $m->orWhere('mobile', 'like', '%'.$digits.'%'))))
            ->when($request->filled('role') && Role::where('name', (string) $request->query('role'))->exists(), fn ($w) => $w->role((string) $request->query('role')))
            ->when(UserStatus::tryFrom((string) $request->query('status')), fn ($w, $s) => $w->where('status', $s));
        // Paged and sorted in SQL (rule P1); every sort column has an index.
        [$sort, $dir] = ListQuery::sort($query, $request, [
            'name' => 'users.name', 'email' => 'users.email', 'status' => 'users.status', 'last_login' => 'users.last_login_at',
        ], key: 'users.id');
        $users = $query->paginate(ListQuery::perPage($request))->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'sort' => $sort,
            'dir' => $dir,
            'sortOptions' => ['name' => 'Name', 'email' => 'Login email', 'status' => 'Status', 'last_login' => 'Last login'],
            'roles' => Role::orderBy('name')->pluck('name'),
            'unitCount' => Unit::count(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['status' => UserStatus::Active]));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return redirect()->route('admin.users.index')->with('toast', 'User '.$user->name.' created');
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.index')->with('toast', 'User '.$user->name.' saved');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->users->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('toast', 'User '.$user->name.' deleted');
    }

    private function form(User $user): View
    {
        $user->loadMissing(['roles', 'units', 'permissions', 'creator', 'updater']);
        $actor = auth()->user();
        $roles = Role::with('permissions')->orderBy('name')->get();

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $roles,
            // Roles this user may give (decisions U8, U9); the user's current role always stays listed.
            'offeredRoles' => $roles->filter(fn (Role $r) => $r->name === $user->roleName() || ($actor->isAdmin()
                || ($r->name !== Roles::ADMIN && ! Access::notGrantable($actor, $r->permissions->pluck('name')->all()))))->values(),
            // Read-only when the signed-in user may not change this user (U8, U9).
            'manageLocked' => $user->exists && ! Access::canManage($actor, $user),
            'units' => Unit::orderBy('code')->get(),
            'history' => ActivityLog::history($user, 8),
        ]);
    }
}
