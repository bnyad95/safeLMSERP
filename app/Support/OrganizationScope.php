<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizationScope
{
    public static function apply(Builder $query, User $user, string $modelType): void
    {
        $roleNames = $user->roles->pluck('name');

        if ($roleNames->contains('super_administrator')) {
            return;
        }

        $scope = match (true) {
            $roleNames->contains('department_administrator') => 'department',
            $roleNames->contains('college_administrator') => 'college',
            $roleNames->contains('university_administrator') => 'university',
            $roleNames->intersect(['examination_administrator', 'examination_committee'])->isNotEmpty() => self::examinationScope($user),
            $roleNames->intersect(['admission_officer', 'receptionist'])->isNotEmpty() => 'department',
            self::hasDirectAcademicSetupGrant($user) => self::assignedScope($user),
            default => null,
        };

        if (! $scope) {
            return;
        }

        if (! $user->university_id || ($scope === 'college' && ! $user->college_id) || ($scope === 'department' && ! $user->department_id)) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($modelType) {
            'university' => $query->whereKey($user->university_id),
            'college' => self::scopeCollege($query, $user, $scope),
            'department' => self::scopeDepartment($query, $user, $scope),
            'student', 'teacher' => self::scopeDirectDepartment($query, $user, $scope),
            'course' => self::scopeThroughDepartment($query, $user, $scope),
            'section' => self::scopeThroughCourse($query, $user, $scope),
            'stage' => self::scopeThroughDepartment($query, $user, $scope),
            'course_record' => self::scopeRecordThroughCourse($query, $user, $scope),
            'student_record' => self::scopeThroughStudent($query, $user, $scope),
            'section_record' => self::scopeThroughSection($query, $user, $scope),
            'semester', 'academic_year' => $query->where('university_id', $user->university_id),
            default => null,
        };
    }

    private static function examinationScope(User $user): ?string
    {
        return self::assignedScope($user);
    }

    private static function assignedScope(User $user): string
    {
        return match (true) {
            filled($user->department_id) => 'department',
            filled($user->college_id) => 'college',
            filled($user->university_id) => 'university',
            default => 'unassigned',
        };
    }

    private static function hasDirectAcademicSetupGrant(User $user): bool
    {
        if (! $user->relationLoaded('permissionOverrides')) {
            $user->load('permissionOverrides');
        }

        return $user->permissionOverrides->contains(function ($permission) {
            return in_array($permission->name, ['academic_setup.view', 'academic_setup.manage'], true)
                && $permission->pivot->effect === 'grant';
        });
    }

    private static function scopeCollege(Builder $query, User $user, string $scope): void
    {
        $query->where('university_id', $user->university_id)
            ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->whereKey($user->college_id));
    }

    private static function scopeDepartment(Builder $query, User $user, string $scope): void
    {
        $query->where('university_id', $user->university_id)
            ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->where('college_id', $user->college_id))
            ->when($scope === 'department', fn (Builder $builder) => $builder->whereKey($user->department_id));
    }

    private static function scopeDirectDepartment(Builder $query, User $user, string $scope): void
    {
        $query->where('university_id', $user->university_id)
            ->when($scope === 'college', fn (Builder $builder) => $builder->whereHas('department', fn (Builder $department) => $department->where('college_id', $user->college_id)))
            ->when($scope === 'department', fn (Builder $builder) => $builder->where('department_id', $user->department_id));
    }

    private static function scopeThroughDepartment(Builder $query, User $user, string $scope): void
    {
        $query->whereHas('department', function (Builder $department) use ($user, $scope) {
            $department->where('university_id', $user->university_id)
                ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->where('college_id', $user->college_id))
                ->when($scope === 'department', fn (Builder $builder) => $builder->whereKey($user->department_id));
        });
    }

    private static function scopeThroughCourse(Builder $query, User $user, string $scope): void
    {
        $query->whereHas('course.department', function (Builder $department) use ($user, $scope) {
            $department->where('university_id', $user->university_id)
                ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->where('college_id', $user->college_id))
                ->when($scope === 'department', fn (Builder $builder) => $builder->whereKey($user->department_id));
        });
    }

    private static function scopeRecordThroughCourse(Builder $query, User $user, string $scope): void
    {
        $query->whereHas('course.department', function (Builder $department) use ($user, $scope) {
            $department->where('university_id', $user->university_id)
                ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->where('college_id', $user->college_id))
                ->when($scope === 'department', fn (Builder $builder) => $builder->whereKey($user->department_id));
        });
    }

    private static function scopeThroughStudent(Builder $query, User $user, string $scope): void
    {
        $query->whereHas('student', function (Builder $student) use ($user, $scope) {
            $student->where('university_id', $user->university_id)
                ->when($scope === 'college', fn (Builder $builder) => $builder->whereHas('department', fn (Builder $department) => $department->where('college_id', $user->college_id)))
                ->when($scope === 'department', fn (Builder $builder) => $builder->where('department_id', $user->department_id));
        });
    }

    private static function scopeThroughSection(Builder $query, User $user, string $scope): void
    {
        $query->whereHas('courseSection.course.department', function (Builder $department) use ($user, $scope) {
            $department->where('university_id', $user->university_id)
                ->when(in_array($scope, ['college', 'department'], true), fn (Builder $builder) => $builder->where('college_id', $user->college_id))
                ->when($scope === 'department', fn (Builder $builder) => $builder->whereKey($user->department_id));
        });
    }
}
