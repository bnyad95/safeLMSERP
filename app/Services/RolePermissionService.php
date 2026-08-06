<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Observers\RolePermissionObserver;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    protected $observer;

    public function __construct(?RolePermissionObserver $observer = null)
    {
        $this->observer = $observer ?? new RolePermissionObserver;
    }

    /**
     * Assign a role to a user
     */
    public function assignRoleToUser(User $user, $roleId)
    {
        $user->roles()->attach($roleId);
        $this->observer->logRoleAttached($user, $roleId);
    }

    /**
     * Remove a role from a user
     */
    public function removeRoleFromUser(User $user, $roleId)
    {
        $user->roles()->detach($roleId);
        $this->observer->logRoleDetached($user, $roleId);
    }

    /**
     * Assign a permission to a role
     */
    public function assignPermissionToRole($roleId, $permissionId)
    {
        $role = Role::find($roleId);
        if ($role) {
            $role->permissions()->attach($permissionId);
            $this->observer->logPermissionAttachedToRole($roleId, $permissionId);
        }
    }

    /**
     * Remove a permission from a role
     */
    public function removePermissionFromRole($roleId, $permissionId)
    {
        $role = Role::find($roleId);
        if ($role) {
            $role->permissions()->detach($permissionId);
            $this->observer->logPermissionDetachedFromRole($roleId, $permissionId);
        }
    }

    /**
     * Sync roles for a user (assign multiple roles at once)
     */
    public function syncUserRoles(User $user, array $roleIds)
    {
        $currentRoleIds = $user->roles()->pluck('roles.id')->toArray();

        $toDetach = array_diff($currentRoleIds, $roleIds);
        $toAttach = array_diff($roleIds, $currentRoleIds);

        $user->roles()->sync($roleIds);

        foreach ($toDetach as $roleId) {
            $this->observer->logRoleDetached($user, $roleId);
        }

        foreach ($toAttach as $roleId) {
            $this->observer->logRoleAttached($user, $roleId);
        }
    }

    public function syncUserPermissionOverrides(User $user, array $overrides): void
    {
        $current = $user->permissionOverrides()
            ->get()
            ->mapWithKeys(fn (Permission $permission) => [$permission->id => $permission->pivot->effect]);
        $next = collect($overrides)->map(fn (array $pivot) => $pivot['effect']);

        foreach ($current->keys()->merge($next->keys())->unique() as $permissionId) {
            $from = $current->get($permissionId);
            $to = $next->get($permissionId);

            if ($from !== $to) {
                $this->observer->logUserPermissionOverrideChanged($user, (int) $permissionId, $from, $to);
            }
        }

        $user->permissionOverrides()->sync($overrides);
    }

    /**
     * Sync permissions for a role (assign multiple permissions at once)
     */
    public function syncRolePermissions($roleId, array $permissionIds): void
    {
        DB::transaction(function () use ($roleId, $permissionIds) {
            $role = Role::query()->lockForUpdate()->find($roleId);
            if (! $role) {
                return;
            }

            $currentPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
            $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
            $toDetach = array_diff($currentPermissionIds, $permissionIds);
            $toAttach = array_diff($permissionIds, $currentPermissionIds);

            $role->permissions()->sync($permissionIds);

            foreach ($toDetach as $permissionId) {
                $this->observer->logPermissionDetachedFromRole($roleId, $permissionId);
            }

            foreach ($toAttach as $permissionId) {
                $this->observer->logPermissionAttachedToRole($roleId, $permissionId);
            }
        });
    }
}
