<?php

namespace App\Support;

final class UserRolePolicy
{
    public const PROFILE_MANAGED_ROLES = ['student', 'teacher'];

    public const EXCLUSIVE_ROLES = ['student', 'teacher', 'parent_user', 'librarian', 'receptionist'];

    public const DEPARTMENT_SCOPED_ROLES = [
        'department_administrator',
        'admission_officer',
        'receptionist',
        'teaching_assistant',
    ];

    public const COLLEGE_SCOPED_ROLES = ['college_administrator'];

    public const UNIVERSITY_SCOPED_ROLES = ['university_administrator', 'university_president'];

    public const FLEXIBLE_SCOPED_ROLES = [
        'examination_administrator',
        'examination_committee',
        'hr_manager',
        'chief_accountant',
        'accountant',
        'lms_administrator',
        'librarian',
        'it_support',
        'parent_user',
    ];

    public const HIGH_RISK_ROLES = [
        'super_administrator',
        'administrator',
        'university_administrator',
        'college_administrator',
        'department_administrator',
        'university_president',
        'chief_accountant',
        'accountant',
        'registrar',
        'admission_officer',
        'hr_manager',
        'lms_administrator',
        'examination_administrator',
        'examination_committee',
        'teaching_assistant',
        'it_support',
    ];

    public const IT_SUPPORT_ASSIGNABLE_ROLES = ['librarian', 'receptionist', 'parent_user'];

    public static function organizationRequirement(array $roleNames): ?string
    {
        if (array_intersect($roleNames, self::DEPARTMENT_SCOPED_ROLES)) {
            return 'department';
        }

        if (array_intersect($roleNames, self::COLLEGE_SCOPED_ROLES)) {
            return 'college';
        }

        if (array_intersect($roleNames, self::UNIVERSITY_SCOPED_ROLES)) {
            return 'university';
        }

        if (array_intersect($roleNames, self::FLEXIBLE_SCOPED_ROLES)) {
            return 'flexible';
        }

        return null;
    }

    public static function organizationRoleScopes(): array
    {
        return collect([
            'department' => self::DEPARTMENT_SCOPED_ROLES,
            'college' => self::COLLEGE_SCOPED_ROLES,
            'university' => self::UNIVERSITY_SCOPED_ROLES,
            'flexible' => self::FLEXIBLE_SCOPED_ROLES,
        ])->flatMap(fn (array $roles, string $scope) => collect($roles)->mapWithKeys(fn (string $role) => [$role => $scope]))
            ->all();
    }

    public static function isHighRisk(string $roleName): bool
    {
        return in_array($roleName, self::HIGH_RISK_ROLES, true);
    }
}
