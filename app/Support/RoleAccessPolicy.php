<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

final class RoleAccessPolicy
{
    private const UNENFORCED_PERMISSIONS = [
        'marks.submit',
        'lms.create_content',
        'lms.manage_courses',
        'reports.academic',
        'reports.financial',
        'reports.audit',
    ];

    private const ROLE_GATES = [
        'finance.view_global' => [],
        'finance.' => ['chief_accountant', 'accountant'],
        'academic_setup.' => ['administrator'],
        'users.' => ['it_support'],
        'attendance.' => [
            'teacher',
            'student',
            'administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'registrar',
        ],
        'marks.review' => ['examination_administrator', 'examination_committee'],
        'marks.approve' => ['examination_administrator', 'examination_committee'],
        'marks.publish' => ['examination_administrator', 'examination_committee'],
        'marks.request_change' => ['examination_administrator', 'examination_committee'],
    ];

    public static function accessFor(Role $role, string $permission, bool $assigned): array
    {
        if ($role->name === 'super_administrator') {
            return [
                'status' => 'implicit',
                'label' => 'Implicit full access',
                'reason' => 'Super Administrator bypasses permission assignments.',
            ];
        }

        if (! $assigned) {
            return [
                'status' => 'missing',
                'label' => 'Not assigned',
                'reason' => 'This permission is not included in the role template.',
            ];
        }

        if (in_array($permission, self::UNENFORCED_PERMISSIONS, true)) {
            return [
                'status' => 'unenforced',
                'label' => 'Not enforced',
                'reason' => 'This permission is stored in the role template, but no route or controller currently checks it.',
            ];
        }

        $allowedRoles = self::allowedRolesFor($permission);

        if ($allowedRoles !== null && ! in_array($role->name, $allowedRoles, true)) {
            return [
                'status' => 'conditional',
                'label' => 'Conditional',
                'reason' => $allowedRoles === []
                    ? 'This capability is recognized only as a direct user grant; assigning it to a role does not unlock access.'
                    : 'Related routes also require an eligible role or a direct user grant; this role assignment alone may not unlock them.',
            ];
        }

        return [
            'status' => 'effective',
            'label' => 'Role-compatible',
            'reason' => $allowedRoles === null
                ? 'The permission is enforced directly where this capability is used.'
                : 'The role and permission gates are compatible.',
        ];
    }

    public static function userAccessFor(User $user, string $permission, bool $effective, ?string $override): array
    {
        if ($override === 'deny') {
            return [
                'status' => 'denied',
                'label' => 'Directly denied',
                'reason' => 'A direct user override removes this permission even when a role grants it.',
            ];
        }

        if (! $effective) {
            return [
                'status' => 'missing',
                'label' => 'No access',
                'reason' => 'Neither the assigned roles nor a direct grant provide this permission.',
            ];
        }

        if (in_array($permission, self::UNENFORCED_PERMISSIONS, true)) {
            return [
                'status' => 'unenforced',
                'label' => 'Not enforced',
                'reason' => 'This permission is stored for the user, but no route or controller currently checks it.',
            ];
        }

        $allowedRoles = self::allowedRolesFor($permission);
        $roleNames = $user->roles->pluck('name');
        $hasCompatibleRole = $allowedRoles === null || $roleNames->intersect($allowedRoles)->isNotEmpty();
        $directGrantBypassesRole = $override === 'grant' && self::directGrantBypassesRoleFor($permission);

        if ($permission === 'finance.view_global' && $override !== 'grant') {
            return [
                'status' => 'conditional',
                'label' => 'Direct grant required',
                'reason' => 'Global finance visibility is recognized only as an explicit direct user grant.',
            ];
        }

        if (! $hasCompatibleRole && ! $directGrantBypassesRole) {
            return [
                'status' => 'conditional',
                'label' => 'Blocked by role gate',
                'reason' => 'The permission is present, but the related routes also require a compatible role.',
            ];
        }

        return [
            'status' => 'effective',
            'label' => 'Effective access',
            'reason' => $override === 'grant'
                ? 'A direct user grant unlocks this enforced capability.'
                : 'The user role and permission gates are compatible.',
        ];
    }

    public static function scopeFor(Role $role): array
    {
        $special = [
            'student' => ['label' => 'Own records', 'detail' => 'Only the signed-in student data and enrolled classes.'],
            'teacher' => ['label' => 'Assigned classes', 'detail' => 'Classes and students assigned to the teacher.'],
            'parent_user' => ['label' => 'Linked students', 'detail' => 'Only students linked to the parent account.'],
        ];

        if (isset($special[$role->name])) {
            return $special[$role->name];
        }

        $scope = UserRolePolicy::organizationRoleScopes()[$role->name] ?? null;

        return match ($scope) {
            'department' => ['label' => 'Department', 'detail' => 'Restricted to the assigned department.'],
            'college' => ['label' => 'College', 'detail' => 'Restricted to the assigned college and its departments.'],
            'university' => ['label' => 'University', 'detail' => 'Restricted to the assigned university.'],
            'flexible' => ['label' => 'Assigned organization', 'detail' => 'Uses the deepest assigned university, college, or department scope.'],
            default => ['label' => 'System-wide', 'detail' => 'The role has no organization restriction by default.'],
        };
    }

    public static function permissionSignature(iterable $permissionIds): string
    {
        $ids = collect($permissionIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return hash('sha256', implode(',', $ids));
    }

    public static function userPermissionSignature(User $user): string
    {
        $user->loadMissing(['roles.permissions', 'permissionOverrides']);
        $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->implode(',');
        $rolePermissionIds = $user->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->implode(',');
        $overrides = $user->permissionOverrides
            ->map(fn ($permission) => ((int) $permission->id).':'.$permission->pivot->effect)
            ->sort()
            ->values()
            ->implode(',');

        return hash('sha256', $user->id.'|'.$roleIds.'|'.$rolePermissionIds.'|'.$overrides);
    }

    private static function allowedRolesFor(string $permission): ?array
    {
        foreach (self::ROLE_GATES as $permissionPattern => $roles) {
            if (str_ends_with($permissionPattern, '.') && str_starts_with($permission, $permissionPattern)) {
                return $roles;
            }

            if ($permission === $permissionPattern) {
                return $roles;
            }
        }

        return null;
    }

    private static function directGrantBypassesRoleFor(string $permission): bool
    {
        return str_starts_with($permission, 'finance.')
            || str_starts_with($permission, 'academic_setup.');
    }
}
