<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Support\PermissionRiskPolicy;
use App\Support\RoleAccessPolicy;
use PHPUnit\Framework\TestCase;

class RoleAccessPolicyTest extends TestCase
{
    public function test_super_administrator_has_implicit_access(): void
    {
        $role = new Role(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);

        $access = RoleAccessPolicy::accessFor($role, 'academic_setup.manage', false);

        $this->assertSame('implicit', $access['status']);
    }

    public function test_role_gated_permission_reports_compatible_and_conditional_roles(): void
    {
        $accountant = new Role(['name' => 'accountant', 'display_name' => 'Finance Officer']);
        $librarian = new Role(['name' => 'librarian', 'display_name' => 'Library Administrator']);

        $this->assertSame('effective', RoleAccessPolicy::accessFor($accountant, 'finance.view', true)['status']);
        $this->assertSame('conditional', RoleAccessPolicy::accessFor($librarian, 'finance.view', true)['status']);
    }

    public function test_direct_only_and_unenforced_permissions_are_not_reported_as_effective(): void
    {
        $accountant = new Role(['name' => 'accountant', 'display_name' => 'Finance Officer']);
        $teacher = new Role(['name' => 'teacher', 'display_name' => 'Teacher']);

        $this->assertSame('conditional', RoleAccessPolicy::accessFor($accountant, 'finance.view_global', true)['status']);
        $this->assertSame('unenforced', RoleAccessPolicy::accessFor($teacher, 'marks.submit', true)['status']);
    }

    public function test_attendance_permissions_respect_the_route_role_gate(): void
    {
        $teacher = new Role(['name' => 'teacher', 'display_name' => 'Teacher']);
        $librarian = new Role(['name' => 'librarian', 'display_name' => 'Library Administrator']);

        $this->assertSame('effective', RoleAccessPolicy::accessFor($teacher, 'attendance.view', true)['status']);
        $this->assertSame('conditional', RoleAccessPolicy::accessFor($librarian, 'attendance.view', true)['status']);
    }

    public function test_organization_scope_uses_the_central_role_policy(): void
    {
        $role = new Role(['name' => 'department_administrator', 'display_name' => 'Department Administrator']);

        $this->assertSame('Department', RoleAccessPolicy::scopeFor($role)['label']);
    }

    public function test_permission_signature_is_order_independent(): void
    {
        $this->assertSame(
            RoleAccessPolicy::permissionSignature([3, 1, 2]),
            RoleAccessPolicy::permissionSignature([2, 3, 1])
        );
    }

    public function test_sensitive_permissions_include_access_finance_and_academic_changes(): void
    {
        $this->assertSame('critical', PermissionRiskPolicy::metadata('users.assign_roles')['level']);
        $this->assertSame('high', PermissionRiskPolicy::metadata('finance.record_expense')['level']);
        $this->assertSame('critical', PermissionRiskPolicy::metadata('academic_setup.manage')['level']);
        $this->assertSame('standard', PermissionRiskPolicy::metadata('courses.view')['level']);
    }
}
