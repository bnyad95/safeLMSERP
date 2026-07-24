<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Observers\RolePermissionObserver;

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

        // Log detachments
        $toDetach = array_diff($currentRoleIds, $roleIds);
        foreach ($toDetach as $roleId) {
            $this->observer->logRoleDetached($user, $roleId);
        }

        // Log attachments
        $toAttach = array_diff($roleIds, $currentRoleIds);
        foreach ($toAttach as $roleId) {
            $this->observer->logRoleAttached($user, $roleId);
        }

        $user->roles()->sync($roleIds);
    }

    /**
     * Sync permissions for a role (assign multiple permissions at once)
     */
    public function syncRolePermissions($roleId, array $permissionIds)
    {
        $role = Role::find($roleId);
        if (! $role) {
            return;
        }

        $currentPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        // Log detachments
        $toDetach = array_diff($currentPermissionIds, $permissionIds);
        foreach ($toDetach as $permissionId) {
            $this->observer->logPermissionDetachedFromRole($roleId, $permissionId);
        }

        // Log attachments
        $toAttach = array_diff($permissionIds, $currentPermissionIds);
        foreach ($toAttach as $permissionId) {
            $this->observer->logPermissionAttachedToRole($roleId, $permissionId);
        }

        $role->permissions()->sync($permissionIds);
    }
}
