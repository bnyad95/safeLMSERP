<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RolePermissionObserver
{
    /**
     * Log when a role is attached to a user
     */
    public function logRoleAttached(User $user, $roleId)
    {
        ActivityLog::create([
            'log_name' => 'role_assignment',
            'description' => 'role_attached',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'role_name' => $user->roles()->where('roles.id', $roleId)->first()?->name,
            ],
        ]);
    }

    /**
     * Log when a role is detached from a user
     */
    public function logRoleDetached(User $user, $roleId)
    {
        ActivityLog::create([
            'log_name' => 'role_assignment',
            'description' => 'role_detached',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'user_id' => $user->id,
                'role_id' => $roleId,
            ],
        ]);
    }

    /**
     * Log when a permission is attached to a role
     */
    public function logPermissionAttachedToRole($roleId, $permissionId)
    {
        ActivityLog::create([
            'log_name' => 'permission_assignment',
            'description' => 'permission_attached_to_role',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ],
        ]);
    }

    /**
     * Log when a permission is detached from a role
     */
    public function logPermissionDetachedFromRole($roleId, $permissionId)
    {
        ActivityLog::create([
            'log_name' => 'permission_assignment',
            'description' => 'permission_detached_from_role',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ],
        ]);
    }

    public function logUserPermissionOverrideChanged(User $user, int $permissionId, ?string $from, ?string $to): void
    {
        ActivityLog::create([
            'log_name' => 'user_permission_override',
            'description' => 'user_permission_override_changed',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'user_id' => $user->id,
                'permission_id' => $permissionId,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
