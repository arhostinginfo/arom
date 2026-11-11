<?php

namespace App\Services;

use App\Support\Roles;
use App\Support\Access;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Creates, changes and deletes roles on Access rights, logging each step (decisions U4, U6). */
class RoleService
{
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $perms = Access::withViews($data['permissions'] ?? []);
            $role->syncPermissions($perms);

            ActivityLogger::log('role.created', $role, 'Created role '.$role->name);
            if ($perms) {
                ActivityLogger::log('role.permissions_updated', $role, 'Gave role '.$role->name.' '.count($perms).' permission(s)', [
                    'added' => Access::labels($perms),
                ]);
            }

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $oldName = $role->name;
            if ($oldName !== $data['name']) {
                // Decision U10: checked here too, so no other caller can rename Admin or Sales.
                if (Roles::isFixed($oldName)) {
                    throw ValidationException::withMessages(['name' => "The $oldName role cannot be renamed."]);
                }
                $role->update(['name' => $data['name']]);
                ActivityLogger::log('role.updated', $role, 'Renamed role '.$oldName.' to '.$role->name, [
                    'Name' => ['old' => $oldName, 'new' => $role->name],
                ]);
            }

            // Admin always keeps every permission.
            if ($role->name !== Roles::ADMIN) {
                // Rights on pages of a part that is switched off are not on the form; they are kept as they are.
                $perms = Access::keepHidden(Access::withViews($data['permissions'] ?? []), $role->permissions->pluck('name')->all());
                if ($changes = ActivityLogger::listDiff($role->permissions->pluck('name')->all(), $perms)) {
                    $role->syncPermissions($perms);
                    ActivityLogger::log('role.permissions_updated', $role, 'Changed permissions of role '.$role->name.' ('.$role->users()->count().' user(s) affected)', array_map([Access::class, 'labels'], $changes));
                }
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        // Decision U10: Admin and Sales are built in.
        if ($role->name === Roles::ADMIN) {
            throw ValidationException::withMessages(['delete' => 'The Admin role cannot be deleted.']);
        }
        if ($role->name === Roles::SALES) {
            throw ValidationException::withMessages(['delete' => 'The Sales role cannot be deleted, because quotations pick their sales person from it.']);
        }
        if (($n = $role->users()->count()) > 0) {
            throw ValidationException::withMessages(['delete' => "Role {$role->name} is given to $n user(s). Give them another role first."]);
        }

        DB::transaction(function () use ($role) {
            ActivityLogger::log('role.deleted', $role, 'Deleted role '.$role->name, [
                'removed' => Access::labels($role->permissions->pluck('name')->all()),
            ]);
            $role->delete();
        });
    }
}
