<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_user_management_index(): void
    {
        $role = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/users')
            ->assertOk();
    }

    public function test_non_super_admin_cannot_access_user_management_index(): void
    {
        Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_and_assign_role(): void
    {
        $superAdminRole = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Teacher',
            'description' => 'Teacher role',
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Teacher',
            'email' => 'teacher@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$teacherRole->id],
        ]);

        $createdUser = User::where('email', 'teacher@example.com')->first();

        $response->assertRedirect(route('users.permissions.edit', $createdUser));

        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->roles()->where('name', 'teacher')->exists());
    }

    public function test_super_admin_can_review_grant_and_deny_user_permissions_after_roles(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Instructor']);
        $studentsView = Permission::create(['name' => 'students.view', 'display_name' => 'View students']);
        $financeView = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $teacherRole->permissions()->attach($studentsView->id);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $teacher = User::factory()->create();
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($admin)
            ->get(route('users.permissions.edit', $teacher))
            ->assertOk()
            ->assertSee('User Permissions')
            ->assertSee('students.view')
            ->assertSee('Role');

        $this->actingAs($admin)
            ->patch(route('users.permissions.update', $teacher), [
                'permission_ids' => [$financeView->id],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('permission_user', [
            'user_id' => $teacher->id,
            'permission_id' => $studentsView->id,
            'effect' => 'deny',
        ]);
        $this->assertDatabaseHas('permission_user', [
            'user_id' => $teacher->id,
            'permission_id' => $financeView->id,
            'effect' => 'grant',
        ]);
        $this->assertFalse($teacher->fresh()->hasPermission('students.view'));
        $this->assertTrue($teacher->fresh()->hasPermission('finance.view'));
    }

    public function test_user_management_filters_and_shows_account_workspace(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Instructor']);
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $teacher = User::factory()->create([
            'name' => 'Filtered Teacher',
            'email' => 'filtered-teacher@example.com',
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($admin)
            ->get(route('users.index', ['q' => 'Filtered', 'role_id' => $teacherRole->id, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('User Management')
            ->assertSee('Active Users')
            ->assertSee('Filtered Teacher')
            ->assertSee('Computer Science');
    }

    public function test_super_admin_can_archive_restore_and_reset_password(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Instructor']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $teacher = User::factory()->create(['email' => 'archive-me@example.com']);
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($admin)
            ->post(route('users.reset-password', $teacher), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('users.edit', $teacher));

        $this->assertTrue(Hash::check('NewPassword123', $teacher->fresh()->password));

        $this->actingAs($admin)
            ->delete(route('users.destroy', $teacher))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $teacher->id]);

        $this->actingAs($admin)
            ->get(route('users.archived'))
            ->assertOk()
            ->assertSee('archive-me@example.com')
            ->assertSee('Restore');

        $this->actingAs($admin)
            ->patch(route('users.restore', $teacher->id))
            ->assertRedirect(route('users.archived'));

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'deleted_at' => null]);
    }

    public function test_it_support_can_manage_safe_user_accounts_only(): void
    {
        $supportRole = Role::create(['name' => 'it_support', 'display_name' => 'Technical Support']);
        foreach (['users.create', 'users.update', 'users.assign_roles', 'users.reset_password'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $supportRole->permissions()->attach($permission->id);
        }
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Instructor']);
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);

        $support = User::factory()->create();
        $support->roles()->attach($supportRole);

        $this->actingAs($support)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('User Management');

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Supported Teacher',
                'email' => 'supported-teacher@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$teacherRole->id],
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'supported-teacher@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('teacher'));

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Blocked Super',
                'email' => 'blocked-super@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$superRole->id],
            ])
            ->assertForbidden();

        $superUser = User::factory()->create(['email' => 'protected-super@example.com']);
        $superUser->roles()->attach($superRole);

        $this->actingAs($support)
            ->get(route('users.edit', $superUser))
            ->assertForbidden();

        $this->actingAs($support)
            ->delete(route('users.destroy', $created))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_archive_self_or_remove_own_super_admin_role(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Instructor']);
        $admin = User::factory()->create(['email' => 'self-admin@example.com']);
        $admin->roles()->attach($superAdminRole->id);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('users.update', $admin), [
                'name' => 'Self Admin',
                'email' => 'self-admin@example.com',
                'roles' => [$teacherRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->hasRole('super_administrator'));
    }
}
