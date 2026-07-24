<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
            ->assertSee('High risk')
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
            ->assertSee('Selected role access')
            ->assertSee('Selected role: <span class="font-medium text-gray-700">Registrar</span>', false)
            ->assertSee('students.view')
            ->assertSee('Registrar')
            ->assertDontSee('students.archive')
            ->assertDontSee('marks.enter')
            ->assertDontSee('<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Teacher</span>', false);

        $this->actingAs($user)
            ->get(route('access-matrix', [
                'role_id' => $registrarRole->id,
                'access' => 'missing',
                'q' => 'students',
            ]))
            ->assertOk()
            ->assertSee('students.archive')
            ->assertSee('Not granted to selected role')
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
}
