<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeacherDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_user_gets_directory_and_profile_without_management_actions(): void
    {
        [$university, , $department] = $this->organization('VIEW');
        $teacher = $this->teacher($university, $department, ['full_name' => 'Read Only Teacher']);
        $user = $this->userWithPermissions('hr_manager', ['teachers.view'], [
            'university_id' => $university->id,
        ]);

        $this->actingAs($user)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Read Only Teacher')
            ->assertSee('View')
            ->assertDontSee('Add Teacher')
            ->assertDontSee('Archive Teacher');

        $this->actingAs($user)
            ->get(route('teachers.show', $teacher))
            ->assertOk()
            ->assertSee('Teacher profile, organization placement, and assigned workload.')
            ->assertSee('Bologna Definition')
            ->assertSee('Module Offerings')
            ->assertDontSee('Edit Profile')
            ->assertDontSee('Archive Teacher');

        $this->actingAs($user)->get(route('teachers.create'))->assertForbidden();
        $this->actingAs($user)->get(route('teachers.edit', $teacher))->assertForbidden();
        $this->actingAs($user)->delete(route('teachers.destroy', $teacher))->assertForbidden();
    }

    public function test_teacher_directory_requires_view_permission(): void
    {
        $user = $this->userWithPermissions('hr_manager', ['teachers.create']);

        $this->actingAs($user)->get(route('teachers.index'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('teachers.index').'"', false);
    }

    public function test_search_status_filter_and_classification_use_all_matching_teachers(): void
    {
        [$university, , $department] = $this->organization('LIST');
        $admin = $this->superAdmin();

        foreach (range(1, 16) as $number) {
            $this->teacher($university, $department, [
                'staff_id' => 'LIST-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'full_name' => 'Directory Teacher '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'email' => "teacher{$number}@example.com",
                'status' => $number === 16 ? 'Inactive' : 'Active',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('16 teachers')
            ->assertSee('16 records');

        $this->actingAs($admin)
            ->get(route('teachers.index', ['q' => 'teacher16@example.com']))
            ->assertOk()
            ->assertSee('Directory Teacher 16')
            ->assertDontSee('Directory Teacher 01');

        $this->actingAs($admin)
            ->get(route('teachers.index', ['status' => 'Inactive']))
            ->assertOk()
            ->assertSee('Directory Teacher 16')
            ->assertDontSee('Directory Teacher 01');
    }

    public function test_scoped_admin_only_sees_and_manages_teachers_in_own_organization(): void
    {
        [$university, $college, $department] = $this->organization('SCOPE');
        $otherDepartment = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Scoped Engineering',
            'code' => 'SCOPE-ENG',
        ]);
        [$otherUniversity, $otherCollege, $outsideDepartment] = $this->organization('OUT');

        $visibleTeacher = $this->teacher($university, $department, [
            'staff_id' => 'SCOPE-1',
            'full_name' => 'Visible Teacher',
            'email' => 'visible.teacher@example.com',
        ]);
        $sameCollegeTeacher = $this->teacher($university, $otherDepartment, [
            'staff_id' => 'SCOPE-2',
            'full_name' => 'Same College Teacher',
            'email' => 'same.college.teacher@example.com',
        ]);
        $outsideTeacher = $this->teacher($otherUniversity, $outsideDepartment, [
            'staff_id' => 'OUT-1',
            'full_name' => 'Outside Teacher',
            'email' => 'outside.teacher@example.com',
        ]);
        $archivedVisible = $this->teacher($university, $department, [
            'staff_id' => 'SCOPE-ARCH',
            'full_name' => 'Visible Archived Teacher',
            'email' => 'visible.archived.teacher@example.com',
        ]);
        $archivedOutside = $this->teacher($otherUniversity, $outsideDepartment, [
            'staff_id' => 'OUT-ARCH',
            'full_name' => 'Outside Archived Teacher',
            'email' => 'outside.archived.teacher@example.com',
        ]);
        $archivedVisible->delete();
        $archivedOutside->delete();

        $collegeAdmin = $this->userWithPermissions('college_administrator', ['teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
        ]);

        $this->actingAs($collegeAdmin)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Visible Teacher')
            ->assertSee('Same College Teacher')
            ->assertSee('Archived (1)')
            ->assertDontSee('Outside Teacher');

        $this->actingAs($collegeAdmin)->get(route('teachers.show', $visibleTeacher))->assertOk();
        $this->actingAs($collegeAdmin)->get(route('teachers.show', $outsideTeacher))->assertNotFound();
        $this->actingAs($collegeAdmin)->get(route('teachers.edit', $outsideTeacher))->assertNotFound();
        $this->actingAs($collegeAdmin)->delete(route('teachers.destroy', $outsideTeacher))->assertNotFound();

        $this->actingAs($collegeAdmin)
            ->get(route('teachers.archived'))
            ->assertOk()
            ->assertSee('Visible Archived Teacher')
            ->assertDontSee('Outside Archived Teacher');

        $this->actingAs($collegeAdmin)
            ->patch(route('teachers.restore', $archivedOutside->id))
            ->assertNotFound();

        $this->actingAs($collegeAdmin)
            ->post(route('teachers.store'), $this->teacherPayload($otherUniversity, $outsideDepartment, [
                'college_id' => $otherCollege->id,
                'staff_id' => 'OUT-CREATE',
                'email' => 'outside.create@example.com',
            ]))
            ->assertNotFound();
    }

    public function test_department_admin_only_sees_own_department_teachers(): void
    {
        [$university, $college, $department] = $this->organization('DEPT');
        $otherDepartment = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Other Department',
            'code' => 'OD',
        ]);
        $visibleTeacher = $this->teacher($university, $department, ['full_name' => 'Department Teacher']);
        $outsideTeacher = $this->teacher($university, $otherDepartment, ['full_name' => 'Other Department Teacher']);

        $departmentAdmin = $this->userWithPermissions('department_administrator', ['teachers.view'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($departmentAdmin)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Department Teacher')
            ->assertDontSee('Other Department Teacher');

        $this->actingAs($departmentAdmin)->get(route('teachers.show', $visibleTeacher))->assertOk();
        $this->actingAs($departmentAdmin)->get(route('teachers.show', $outsideTeacher))->assertNotFound();
    }

    public function test_hr_and_custom_teacher_managers_are_limited_to_their_assigned_organization(): void
    {
        [$university, , $department] = $this->organization('HR-SCOPE');
        [$outsideUniversity, , $outsideDepartment] = $this->organization('HR-OUT');
        $this->teacher($university, $department, ['full_name' => 'Scoped HR Teacher']);
        $this->teacher($outsideUniversity, $outsideDepartment, ['full_name' => 'Outside HR Teacher']);

        $hrManager = $this->userWithPermissions('hr_manager', ['teachers.view'], [
            'university_id' => $university->id,
        ]);
        $customManager = $this->userWithPermissions('custom_teacher_manager', ['teachers.view'], [
            'university_id' => $university->id,
        ]);

        foreach ([$hrManager, $customManager] as $manager) {
            $this->actingAs($manager)
                ->get(route('teachers.index'))
                ->assertOk()
                ->assertSee('Scoped HR Teacher')
                ->assertDontSee('Outside HR Teacher');
        }
    }

    public function test_teacher_requires_department_and_account_is_securely_provisioned_and_synced(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('SYNC');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('teachers.store'), $this->teacherPayload($university, $department, ['department_id' => null]))
            ->assertSessionHasErrors('department_id');

        $this->actingAs($admin)
            ->post(route('teachers.store'), $this->teacherPayload($university, $department, ['college_id' => null]))
            ->assertSessionHasErrors('college_id');

        $this->actingAs($admin)
            ->post(route('teachers.store'), $this->teacherPayload($university, $department, [
                'full_name' => 'Original Teacher',
                'email' => 'original.teacher@example.com',
                'password' => 'TempPass@123',
                'password_confirmation' => 'TempPass@123',
            ]))
            ->assertRedirect(route('teachers.index'));

        $teacher = Teacher::where('email', 'original.teacher@example.com')->firstOrFail();
        $account = User::findOrFail($teacher->user_id);

        $this->assertTrue(Hash::check('TempPass@123', $account->password));
        $this->assertTrue($account->must_change_password);

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'full_name' => 'Updated Teacher',
                'email' => 'updated.teacher@example.com',
            ]))
            ->assertRedirect(route('teachers.show', $teacher));

        $account->refresh();
        $this->assertSame('Updated Teacher', $account->name);
        $this->assertSame('updated.teacher@example.com', $account->email);
        $this->assertSame($university->id, $account->university_id);
        $this->assertSame($department->id, $account->department_id);
    }

    public function test_creating_teacher_cannot_overwrite_an_existing_login_account(): void
    {
        [$university, , $department] = $this->organization('ACCOUNT');
        $admin = $this->superAdmin();
        $existingUser = User::factory()->create([
            'name' => 'Protected Account',
            'email' => 'protected@example.com',
            'password' => 'OriginalPassword@123',
        ]);

        $this->actingAs($admin)
            ->post(route('teachers.store'), $this->teacherPayload($university, $department, [
                'full_name' => 'Attempted Teacher',
                'email' => $existingUser->email,
                'password' => 'ReplacementPassword@123',
                'password_confirmation' => 'ReplacementPassword@123',
            ]))
            ->assertSessionHasErrors('email');

        $existingUser->refresh();
        $this->assertSame('Protected Account', $existingUser->name);
        $this->assertTrue(Hash::check('OriginalPassword@123', $existingUser->password));
        $this->assertFalse($existingUser->roles()->where('name', 'teacher')->exists());
        $this->assertDatabaseMissing('teachers', ['email' => $existingUser->email]);
    }

    public function test_updating_legacy_teacher_without_login_creates_recovery_account(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('LEGACY');
        $admin = $this->superAdmin();
        $teacher = $this->teacher($university, $department, [
            'full_name' => 'Legacy Teacher',
            'email' => 'legacy.teacher@example.com',
            'user_id' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
            ]))
            ->assertRedirect(route('teachers.show', $teacher));

        $teacher->refresh();
        $account = User::findOrFail($teacher->user_id);
        $this->assertSame($teacher->email, $account->email);
        $this->assertTrue($account->roles()->where('name', 'teacher')->exists());
    }

    public function test_inactive_teacher_login_is_suspended_and_restored_when_reactivated(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('ACCESS');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('teachers.store'), $this->teacherPayload($university, $department, [
            'full_name' => 'Access Teacher',
            'email' => 'access.teacher@example.com',
        ]));
        $teacher = Teacher::where('email', 'access.teacher@example.com')->firstOrFail();
        $userId = $teacher->user_id;

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'status' => 'Inactive',
            ]))
            ->assertRedirect(route('teachers.show', $teacher));

        $this->assertSoftDeleted('users', ['id' => $userId]);

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'status' => 'Active',
            ]))
            ->assertRedirect(route('teachers.show', $teacher));

        $this->assertDatabaseHas('users', ['id' => $userId, 'deleted_at' => null]);
        $this->assertTrue(User::findOrFail($userId)->roles()->where('name', 'teacher')->exists());
    }

    public function test_open_classes_block_retirement_and_archive_until_reassigned_or_closed(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('LOAD');
        $admin = $this->superAdmin();
        $teacher = $this->teacher($university, $department);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'LOAD101', 'name' => 'Teaching Load', 'credits' => 3, 'semester' => 'Fall']);
        $section = CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'section_code' => 'A', 'status' => 'active']);

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'staff_id' => $teacher->staff_id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'status' => 'Inactive',
            ]))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), $this->teacherPayload($university, $department, [
                'staff_id' => $teacher->staff_id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'status' => 'Retired',
            ]))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->delete(route('teachers.destroy', $teacher))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('teachers', ['id' => $teacher->id]);

        $section->update(['status' => 'closed']);
        $this->actingAs($admin)->delete(route('teachers.destroy', $teacher))->assertRedirect(route('teachers.index'));
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
    }

    public function test_archiving_and_restoring_teacher_suspends_and_restores_teacher_login(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('ARCH');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('teachers.store'), $this->teacherPayload($university, $department, [
            'full_name' => 'Archive Teacher',
            'email' => 'archive.teacher@example.com',
        ]));

        $teacher = Teacher::where('email', 'archive.teacher@example.com')->firstOrFail();
        $userId = $teacher->user_id;

        $this->actingAs($admin)->delete(route('teachers.destroy', $teacher))->assertRedirect(route('teachers.index'));
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->actingAs($admin)->get(route('teachers.archived'))->assertOk()->assertSee('Archive Teacher');

        $this->actingAs($admin)->patch(route('teachers.restore', $teacher->id))->assertRedirect(route('teachers.archived'));
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $userId, 'deleted_at' => null]);
        $this->assertTrue(User::findOrFail($userId)->roles()->where('name', 'teacher')->exists());
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_administrator'], ['display_name' => 'Super Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function userWithPermissions(string $roleName, array $permissionNames, array $attributes = []): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName], ['display_name' => str($permissionName)->replace('.', ' ')->title()]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }

    private function organization(string $suffix): array
    {
        $university = University::create(['name' => "University {$suffix}", 'code' => "U{$suffix}"]);
        $college = College::create(['university_id' => $university->id, 'name' => "College {$suffix}", 'code' => "C{$suffix}"]);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => "Department {$suffix}", 'code' => "D{$suffix}"]);

        return [$university, $college, $department];
    }

    private function teacher(University $university, Department $department, array $attributes = []): Teacher
    {
        return Teacher::create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-'.str()->random(8),
            'full_name' => 'Directory Teacher',
            'email' => str()->random(8).'@example.com',
            'title' => 'Lecturer',
            'status' => 'Active',
        ], $attributes));
    }

    private function teacherPayload(University $university, Department $department, array $attributes = []): array
    {
        return array_merge([
            'full_name' => 'Directory Teacher',
            'staff_id' => 'DIR-100',
            'email' => 'directory.teacher@example.com',
            'password' => 'TeacherTemp@123',
            'password_confirmation' => 'TeacherTemp@123',
            'title' => 'Lecturer',
            'university_id' => $university->id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
            'status' => 'Active',
        ], $attributes);
    }
}
