<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Support\RoleAccessPolicy;
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
            ->assertOk()
            ->assertSee('Global');
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

        $librarianRole = Role::create([
            'name' => 'librarian',
            'display_name' => 'Library Administrator',
            'description' => 'Library workspace',
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $university = University::create(['name' => 'Library University', 'code' => 'LIB']);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Librarian',
            'email' => 'librarian@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$librarianRole->id],
            'university_id' => $university->id,
        ]);

        $createdUser = User::where('email', 'librarian@example.com')->first();

        $response->assertRedirect(route('users.permissions.edit', $createdUser));

        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->roles()->where('name', 'librarian')->exists());
        $this->assertTrue($createdUser->must_change_password);
        $this->assertNotNull($createdUser->email_verified_at);
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'role_attached',
            'subject_type' => User::class,
            'subject_id' => $createdUser->id,
        ]);
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
                'permission_signature' => RoleAccessPolicy::userPermissionSignature($teacher),
                'confirm_permission_change' => '1',
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
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'user_permission_override_changed',
            'subject_type' => User::class,
            'subject_id' => $teacher->id,
        ]);
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
            ->assertSee('Available Accounts')
            ->assertSee('Filtered Teacher')
            ->assertSee('Computer Science');
    }

    public function test_super_admin_can_archive_restore_and_reset_password(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $librarian = User::factory()->create(['email' => 'archive-me@example.com']);
        $librarian->roles()->attach($librarianRole->id);

        $this->actingAs($admin)
            ->post(route('users.reset-password', $librarian), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('users.edit', $librarian));

        $this->assertTrue(Hash::check('NewPassword123', $librarian->fresh()->password));
        $this->assertTrue($librarian->fresh()->must_change_password);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $librarian))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $librarian->id]);

        $this->actingAs($admin)
            ->get(route('users.archived'))
            ->assertOk()
            ->assertSee('archive-me@example.com')
            ->assertSee('Restore');

        $this->actingAs($admin)
            ->patch(route('users.restore', $librarian->id))
            ->assertRedirect(route('users.archived'));

        $this->assertDatabaseHas('users', ['id' => $librarian->id, 'deleted_at' => null]);
    }

    public function test_it_support_can_manage_safe_user_accounts_only(): void
    {
        $supportRole = Role::create(['name' => 'it_support', 'display_name' => 'Technical Support']);
        foreach (['users.create', 'users.update', 'users.assign_roles', 'users.reset_password'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $supportRole->permissions()->attach($permission->id);
        }
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $accountantRole = Role::create(['name' => 'accountant', 'display_name' => 'Accountant']);
        $teachingAssistantRole = Role::create(['name' => 'teaching_assistant', 'display_name' => 'Teaching Assistant']);

        $university = University::create(['name' => 'Support University', 'code' => 'SUP']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Support College', 'code' => 'SC']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Support Department', 'code' => 'SD']);

        $support = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $support->roles()->attach($supportRole);

        $this->actingAs($support)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('User Management');

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Supported Librarian',
                'email' => 'supported-librarian@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$librarianRole->id],
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'supported-librarian@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('librarian'));

        $this->actingAs($support)
            ->put(route('users.update', $created), [
                'name' => 'Updated Librarian',
                'email' => $created->email,
                'roles' => [$librarianRole->id],
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame('Updated Librarian', $created->fresh()->name);

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Blocked Super',
                'email' => 'blocked-super@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$superRole->id],
            ])
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Blocked Teaching Assistant',
                'email' => 'blocked-ta@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$teachingAssistantRole->id],
            ])
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('users.store'), [
                'name' => 'Blocked Accountant',
                'email' => 'blocked-accountant@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$accountantRole->id],
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

    public function test_profile_managed_roles_cannot_be_created_or_changed_from_generic_users(): void
    {
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superRole);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Detached Teacher',
                'email' => 'detached-teacher@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => [$teacherRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $teacher = User::factory()->create(['email' => 'linked-teacher@example.com']);
        $teacher->roles()->attach($teacherRole);

        $this->actingAs($admin)
            ->put(route('users.update', $teacher), [
                'name' => 'Changed Outside Profile',
                'email' => 'linked-teacher@example.com',
                'roles' => [$teacherRole->id],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $teacher))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $teacher->id]);
    }

    public function test_department_scoped_role_inherits_its_full_organization_chain(): void
    {
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admissionRole = Role::create(['name' => 'admission_officer', 'display_name' => 'Admission Officer']);
        $university = University::create(['name' => 'Scope University', 'code' => 'SU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Scope College', 'code' => 'SC']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Scope Department', 'code' => 'SD']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superRole);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Missing Scope Admission',
            'email' => 'missing-scope@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$admissionRole->id],
        ])->assertSessionHasErrors('department_id');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Scoped Admission',
            'email' => 'scoped-admission@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$admissionRole->id],
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $created = User::where('email', 'scoped-admission@example.com')->firstOrFail();
        $this->assertSame($university->id, $created->university_id);
        $this->assertSame($college->id, $created->college_id);
        $this->assertSame($department->id, $created->department_id);
    }

    public function test_it_support_cannot_manage_a_user_with_a_direct_permission_grant(): void
    {
        $supportRole = Role::create(['name' => 'it_support', 'display_name' => 'Technical Support']);
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        foreach (['users.update', 'users.assign_roles', 'users.reset_password'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $supportRole->permissions()->attach($permission);
        }
        $publishPermission = Permission::create(['name' => 'marks.publish', 'display_name' => 'Publish marks']);
        $department = Department::factory()->create();
        $support = User::factory()->create([
            'university_id' => $department->university_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $support->roles()->attach($supportRole);
        $protected = User::factory()->create([
            'university_id' => $department->university_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $protected->roles()->attach($librarianRole);
        $protected->permissionOverrides()->attach($publishPermission, ['effect' => 'grant']);

        $this->actingAs($support)
            ->get(route('users.edit', $protected))
            ->assertForbidden();
    }

    public function test_admin_password_reset_invalidates_an_existing_file_session(): void
    {
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superRole);
        $librarian = User::factory()->create();
        $librarian->roles()->attach($librarianRole);
        $oldFingerprint = hash('sha256', $librarian->getAuthPassword());

        $this->actingAs($admin)
            ->post(route('users.reset-password', $librarian), [
                'password' => 'ChangedPassword123',
                'password_confirmation' => 'ChangedPassword123',
            ])
            ->assertRedirect(route('users.edit', $librarian));

        $librarian->refresh();

        $this->actingAs($librarian)->withSession([
            'auth.password_fingerprint' => $oldFingerprint,
            'auth.password_fingerprint_user_id' => $librarian->id,
        ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
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

    public function test_super_admin_can_archive_or_demote_a_fellow_super_admin_leaving_one_remaining(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $administratorRole = Role::create(['name' => 'administrator', 'display_name' => 'Administrator']);
        $actingAdmin = User::factory()->create(['email' => 'acting-admin@example.com']);
        $actingAdmin->roles()->attach($superAdminRole->id);
        $otherAdmin = User::factory()->create(['email' => 'other-admin@example.com']);
        $otherAdmin->roles()->attach($superAdminRole->id);

        $this->actingAs($actingAdmin)
            ->patch(route('users.update', $otherAdmin), [
                'name' => $otherAdmin->name,
                'email' => 'other-admin@example.com',
                'roles' => [$administratorRole->id],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse($otherAdmin->fresh()->hasRole('super_administrator'));
        $this->assertTrue($actingAdmin->fresh()->hasRole('super_administrator'));

        $thirdAdmin = User::factory()->create(['email' => 'third-admin@example.com']);
        $thirdAdmin->roles()->attach($superAdminRole->id);

        $this->actingAs($actingAdmin)
            ->delete(route('users.destroy', $thirdAdmin))
            ->assertRedirect(route('users.index'));

        $this->assertNotNull($thirdAdmin->fresh()->deleted_at);
        $this->assertTrue($actingAdmin->fresh()->hasRole('super_administrator'));
    }

    public function test_super_admin_can_approve_a_pending_account_deletion_request(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $requester = User::factory()->create(['deletion_requested_at' => now()]);

        $this->actingAs($admin)
            ->post(route('users.deletion.approve', $requester))
            ->assertRedirect(route('users.index'));

        $requester->refresh();
        $this->assertTrue($requester->trashed());
        $this->assertNull($requester->deletion_requested_at);
    }

    public function test_super_admin_can_reject_a_pending_account_deletion_request(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $requester = User::factory()->create(['deletion_requested_at' => now()]);

        $this->actingAs($admin)
            ->post(route('users.deletion.reject', $requester))
            ->assertRedirect(route('users.index'));

        $requester->refresh();
        $this->assertFalse($requester->trashed());
        $this->assertNull($requester->deletion_requested_at);
    }

    public function test_deletion_approval_endpoints_404_when_no_request_is_pending(): void
    {
        $superAdminRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdminRole->id);
        $noRequest = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.deletion.approve', $noRequest))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('users.deletion.reject', $noRequest))
            ->assertNotFound();
    }

    public function test_permission_update_rejects_a_stale_access_form(): void
    {
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $permission = Permission::create(['name' => 'lms.view', 'display_name' => 'View LMS']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superRole);
        $user = User::factory()->create();
        $user->roles()->attach($librarianRole);
        $staleSignature = RoleAccessPolicy::userPermissionSignature($user);
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);

        $this->actingAs($admin)
            ->patch(route('users.permissions.update', $user), [
                'permission_ids' => [$permission->id],
                'permission_signature' => $staleSignature,
                'confirm_permission_change' => '1',
            ])
            ->assertSessionHasErrors('permissions');
    }

    public function test_super_admin_cannot_combine_two_privileged_operational_roles(): void
    {
        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $financeRole = Role::create(['name' => 'accountant', 'display_name' => 'Accountant']);
        $lmsRole = Role::create(['name' => 'lms_administrator', 'display_name' => 'LMS Administrator']);
        $university = University::create(['name' => 'Privilege University', 'code' => 'PRV']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superRole);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Conflicting Operator',
            'email' => 'conflicting@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$financeRole->id, $lmsRole->id],
            'university_id' => $university->id,
        ])->assertSessionHasErrors('roles');
    }

    public function test_it_support_cannot_assign_a_user_outside_its_organization(): void
    {
        $supportRole = Role::create(['name' => 'it_support', 'display_name' => 'Technical Support']);
        foreach (['users.create', 'users.assign_roles'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $supportRole->permissions()->attach($permission);
        }
        $librarianRole = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $ownDepartment = Department::factory()->create();
        $outsideDepartment = Department::factory()->create();
        $support = User::factory()->create([
            'university_id' => $ownDepartment->university_id,
            'college_id' => $ownDepartment->college_id,
            'department_id' => $ownDepartment->id,
        ]);
        $support->roles()->attach($supportRole);

        $this->actingAs($support)->post(route('users.store'), [
            'name' => 'Outside Librarian',
            'email' => 'outside-librarian@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => [$librarianRole->id],
            'department_id' => $outsideDepartment->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'outside-librarian@example.com']);
    }
}
