<?php

namespace App\Services;

use App\Support\Roles;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Access;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Creates, changes and deletes (soft deletes, decision U7) users. Every step writes its own activity log row (decision U6). */
class UserService
{
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = new User($this->attributes($data));
            $user->password = $data['password'];
            $user->created_by = $actor->id;
            $user->updated_by = $actor->id;
            $user->save();
            $user->units()->sync($data['units']);
            $user->syncRoles([$data['role']]);
            $extra = $this->extraPermissions($data['role'], $data['permissions'] ?? []);
            $user->syncPermissions($extra);

            ActivityLogger::log('user.created', $user, 'Created user '.$user->name, ['fields' => $this->snapshot($user)]);
            ActivityLogger::log('user.role_assigned', $user, 'Assigned role '.$data['role'].' to '.$user->name, [
                'role' => ['old' => null, 'new' => $data['role']],
            ]);
            if ($extra) {
                ActivityLogger::log('user.permissions_updated', $user, 'Gave '.$user->name.' '.count($extra).' extra permission(s)', [
                    'added' => Access::labels($extra),
                ]);
            }

            return $user;
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        $this->ensureCanManage($actor, $user);

        return DB::transaction(function () use ($user, $data, $actor) {
            // Same last-active-Admin rule as UserRequest, checked again with the Admin rows locked (A7).
            $leavesAdmin = $data['role'] !== Roles::ADMIN || $data['status'] !== UserStatus::Active->value;
            if ($leavesAdmin && $user->hasRole(Roles::ADMIN) && $user->isActive() && User::activeAdminCount(lock: true) <= 1) {
                throw ValidationException::withMessages(['role' => 'This is the only active Admin. Make another user an active Admin first.']);
            }
            $before = $this->snapshot($user);
            $oldRole = $user->roleName();
            $oldPerms = $user->getDirectPermissions()->pluck('name')->all();

            $user->fill($this->attributes($data));
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->updated_by = $actor->id;
            $user->save();
            $user->units()->sync($data['units']);
            $user->load('units');

            if ($changes = ActivityLogger::diff($before, $this->snapshot($user))) {
                ActivityLogger::log('user.updated', $user, 'Updated user '.$user->name, $changes);
            }
            if (! empty($data['password'])) {
                ActivityLogger::log('user.password_changed', $user, 'Set a new password for '.$user->name);
            }
            if ($oldRole !== $data['role']) {
                $user->syncRoles([$data['role']]);
                ActivityLogger::log('user.role_assigned', $user, 'Changed role of '.$user->name.' from '.$oldRole.' to '.$data['role'], [
                    'role' => ['old' => $oldRole, 'new' => $data['role']],
                ]);
            }
            // Extra rights on pages of a part that is switched off are not on the form; they are kept as they are.
            $extra = $this->extraPermissions($data['role'], Access::keepHidden($data['permissions'] ?? [], $oldPerms));
            if ($permChanges = ActivityLogger::listDiff($oldPerms, $extra)) {
                $user->syncPermissions($extra);
                ActivityLogger::log('user.permissions_updated', $user, 'Changed extra permissions of '.$user->name, array_map([Access::class, 'labels'], $permChanges));
            }

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['delete' => 'You cannot delete your own user.']);
        }
        $this->ensureCanManage($actor, $user);
        DB::transaction(function () use ($user) {
            // Only an active Admin counts towards keeping one active Admin (B3). Checked with the
            // Admin rows locked, inside the transaction, so two deletes cannot both pass (A7).
            if ($user->hasRole(Roles::ADMIN) && $user->isActive() && User::activeAdminCount(lock: true) <= 1) {
                throw ValidationException::withMessages(['delete' => 'This is the only active Admin, so it cannot be deleted.']);
            }

            ActivityLogger::log('user.deleted', $user, 'Deleted user '.$user->name, [
                'fields' => $this->snapshot($user),
                'role' => ['old' => $user->roleName(), 'new' => null],
            ]);
            $user->delete();
        });
    }

    /** Decisions U8 and U9: Admin users only by an Admin; others only when the actor holds all their rights. */
    private function ensureCanManage(User $actor, User $user): void
    {
        if (Access::canManage($actor, $user)) {
            return;
        }
        throw ValidationException::withMessages(['delete' => $user->isAdmin()
            ? 'Only an Admin can change or delete an Admin user.'
            : 'You can only change or delete users whose rights you have yourself.']);
    }

    /** Values shown in the log: what the Admin sees on the form. */
    public function snapshot(User $user): array
    {
        return [
            'Name' => $user->name,
            'Login email' => $user->email,
            'Mobile' => $user->mobileDisplay(),
            'Status' => $user->status?->value,
            'Units allowed' => $user->units->pluck('name')->implode(', '),
        ];
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'status' => $data['status'],
        ];
    }

    /** Only permissions the role does not already give are stored on the user (decision U3). */
    private function extraPermissions(string $role, array $permissions): array
    {
        if ($role === Roles::ADMIN) {
            return [];
        }
        $fromRole = Role::findByName($role)->permissions->pluck('name')->all();

        return array_values(array_diff(Access::withViews($permissions), $fromRole));
    }
}
