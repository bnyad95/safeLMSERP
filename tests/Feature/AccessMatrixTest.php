<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_access_matrix_page(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $permission = Permission::create([
            'name' => 'students.view',
            'display_name' => 'View students',
            'description' => 'View students',
        ]);

        $superAdmin->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($superAdmin->id);

        $this->actingAs($user)
            ->get(route('access-matrix'))
            ->assertOk()
            ->assertSee('Role Access Matrix')
            ->assertSee('Super Administrator')
            ->assertSee('students.view');
    }

    public function test_super_admin_can_filter_permissions_by_module(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $usersPermission = Permission::create([
            'name' => 'users.assign_roles',
            'display_name' => 'Assign roles',
            'description' => 'Assign roles',
        ]);

        Permission::create([
            'name' => 'students.view',
            'display_name' => 'View students',
            'description' => 'View students',
        ]);

        $superAdmin->permissions()->attach($usersPermission->id);

        $user = User::factory()->create();
        $user->roles()->attach($superAdmin->id);

        $this->actingAs($user)
            ->get(route('access-matrix', ['module' => 'users']))
            ->assertOk()
            ->assertSee('Users &amp; Access', false)
            ->assertSee('users.assign_roles')
            ->assertSee('Critical')
            ->assertDontSee('students.view');
    }

    public function test_role_access_and_search_filters_work_together(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $registrarRole = Role::create([
            'name' => 'registrar',
            'display_name' => 'Registrar',
            'description' => 'Registrar role',
        ]);

        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Teacher',
            'description' => 'Teacher role',
        ]);

        $studentsPermission = Permission::create([
            'name' => 'students.view',
            'display_name' => 'View students',
            'description' => 'View students',
        ]);

        $archivePermission = Permission::create([
            'name' => 'students.archive',
            'display_name' => 'Archive students',
            'description' => 'Archive students',
        ]);

        $marksPermission = Permission::create([
            'name' => 'marks.enter',
            'display_name' => 'Enter marks',
            'description' => 'Enter marks',
        ]);

        $registrarRole->permissions()->attach($studentsPermission->id);
        $teacherRole->permissions()->attach($marksPermission->id);

        $user = User::factory()->create();
        $user->roles()->attach($superAdmin->id);

        $this->actingAs($user)
            ->get(route('access-matrix', [
                'role_id' => $registrarRole->id,
                'access' => 'granted',
                'q' => 'students',
            ]))
            ->assertOk()
            ->assertSee('Role assignment')
            ->assertSee('Selected role:')
            ->assertSee('students.view')
            ->assertSee('Registrar')
            ->assertDontSee('students.archive')
            ->assertDontSee('marks.enter');

        $this->actingAs($user)
            ->get(route('access-matrix', [
                'role_id' => $registrarRole->id,
                'access' => 'missing',
                'q' => 'students',
            ]))
            ->assertOk()
            ->assertSee('students.archive')
            ->assertSee('Not assigned')
            ->assertDontSee('students.view')
            ->assertDontSee('marks.enter');

        $this->actingAs($user)
            ->get(route('access-matrix', ['q' => 'archive']))
            ->assertOk()
            ->assertSee('students.archive')
            ->assertDontSee('students.view')
            ->assertDontSee('marks.enter');
    }

    public function test_super_admin_can_update_editable_role_permissions(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Teacher',
            'description' => 'Teacher role',
        ]);

        $coursesPermission = Permission::create([
            'name' => 'courses.view',
            'display_name' => 'View courses',
            'description' => 'View courses',
        ]);

        $marksPermission = Permission::create([
            'name' => 'marks.enter',
            'display_name' => 'Enter marks',
            'description' => 'Enter marks',
        ]);

        $teacherRole->permissions()->attach($marksPermission->id);

        $user = User::factory()->create();
        $user->roles()->attach($superAdmin->id);

        $this->actingAs($user)
            ->patch(route('access-matrix.roles.permissions.update', $teacherRole), [
                'permission_ids' => [$coursesPermission->id],
                'permission_signature' => RoleAccessPolicy::permissionSignature([$marksPermission->id]),
                'confirm_permission_change' => '1',
            ])
            ->assertRedirect(route('access-matrix', ['role_id' => $teacherRole->id, 'mode' => 'edit']));

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $teacherRole->id,
            'permission_id' => $coursesPermission->id,
        ]);

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $teacherRole->id,
            'permission_id' => $marksPermission->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'permission_assignment',
            'description' => 'permission_attached_to_role',
            'subject_id' => $teacherRole->id,
            'causer_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'permission_assignment',
            'description' => 'permission_detached_from_role',
            'subject_id' => $teacherRole->id,
            'causer_id' => $user->id,
        ]);
    }

    public function test_super_administrator_role_permissions_are_read_only(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $permission = Permission::create([
            'name' => 'users.assign_roles',
            'display_name' => 'Assign roles',
            'description' => 'Assign roles',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($superAdmin->id);

        $this->actingAs($user)
            ->patch(route('access-matrix.roles.permissions.update', $superAdmin), [
                'permission_ids' => [$permission->id],
                'confirm_permission_change' => '1',
            ])
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_access_access_matrix_page(): void
    {
        Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Instructor',
            'description' => 'Teacher role',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($teacherRole->id);

        $this->actingAs($user)
            ->get(route('access-matrix'))
            ->assertForbidden();
    }

    public function test_matrix_distinguishes_assignment_route_compatibility_scope_and_overrides(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $departmentAdmin = Role::create(['name' => 'department_administrator', 'display_name' => 'Department Administrator']);
        $financeView = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $departmentAdmin->permissions()->attach($financeView);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin);
        $scopedUser = User::factory()->create();
        $scopedUser->roles()->attach($departmentAdmin);
        $scopedUser->permissionOverrides()->attach($financeView, ['effect' => 'deny']);

        $this->actingAs($admin)
            ->get(route('access-matrix', ['role_id' => $departmentAdmin->id]))
            ->assertOk()
            ->assertSee('Department Administrator impact')
            ->assertSee('Department')
            ->assertSee('1')
            ->assertSee('Conditional')
            ->assertSee('eligible role or a direct user grant')
            ->assertSee('-1 denies');
    }

    public function test_matrix_flags_permissions_that_are_stored_but_not_enforced(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacher = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $permission = Permission::create(['name' => 'marks.submit', 'display_name' => 'Submit marks']);
        $teacher->permissions()->attach($permission);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin);

        $this->actingAs($admin)
            ->get(route('access-matrix', ['role_id' => $teacher->id]))
            ->assertOk()
            ->assertSee('Not enforced')
            ->assertSee('no route or controller currently checks it');
    }

    public function test_super_administrator_access_is_displayed_as_implicit(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        Permission::create(['name' => 'academic_setup.manage', 'display_name' => 'Manage academic setup']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin);

        $this->actingAs($admin)
            ->get(route('access-matrix', ['role_id' => $superAdmin->id]))
            ->assertOk()
            ->assertSee('Implicit full access')
            ->assertSee('Super Administrator bypasses permission assignments');
    }

    public function test_risk_filter_uses_complete_central_risk_metadata(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        Permission::create(['name' => 'academic_setup.manage', 'display_name' => 'Manage academic setup']);
        Permission::create(['name' => 'finance.record_expense', 'display_name' => 'Record expenses']);
        Permission::create(['name' => 'students.view', 'display_name' => 'View students']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin);

        $this->actingAs($admin)
            ->get(route('access-matrix', ['risk' => 'critical']))
            ->assertOk()
            ->assertSee('academic_setup.manage')
            ->assertSee('Critical')
            ->assertDontSee('finance.record_expense')
            ->assertDontSee('students.view');

        $this->actingAs($admin)
            ->get(route('access-matrix', ['risk' => 'high']))
            ->assertOk()
            ->assertSee('finance.record_expense')
            ->assertSee('High risk')
            ->assertDontSee('academic_setup.manage');
    }

    public function test_stale_role_permission_edit_is_rejected_without_overwriting_changes(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacher = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $marksEnter = Permission::create(['name' => 'marks.enter', 'display_name' => 'Enter marks']);
        $coursesView = Permission::create(['name' => 'courses.view', 'display_name' => 'View courses']);
        $teacher->permissions()->attach($marksEnter);
        $staleSignature = RoleAccessPolicy::permissionSignature([$marksEnter->id]);
        $teacher->permissions()->attach($coursesView);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin);

        $this->actingAs($admin)
            ->patch(route('access-matrix.roles.permissions.update', $teacher), [
                'permission_ids' => [$marksEnter->id],
                'permission_signature' => $staleSignature,
                'confirm_permission_change' => '1',
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseHas('permission_role', ['role_id' => $teacher->id, 'permission_id' => $marksEnter->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $teacher->id, 'permission_id' => $coursesView->id]);
    }
}
