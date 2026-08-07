<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_user_gets_directory_and_profile_without_management_actions(): void
    {
        [$university, $college, $department] = $this->organization('VIEW');
        $user = $this->userWithPermissions('registrar', ['students.view'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $student = $this->student($university, $department, [
            'full_name' => 'Read Only Student',
        ]);

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Read Only Student')
            ->assertSee('View')
            ->assertDontSee('Add Student')
            ->assertDontSee('Archive Student');

        $this->actingAs($user)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Read-only student profile')
            ->assertDontSee('Edit Profile')
            ->assertDontSee('Archive Student');

        $this->actingAs($user)->get(route('students.create'))->assertForbidden();
        $this->actingAs($user)->get(route('students.edit', $student))->assertForbidden();
        $this->actingAs($user)->delete(route('students.destroy', $student))->assertForbidden();
    }

    public function test_registrar_sees_students_from_all_departments(): void
    {
        [$university, $college, $department] = $this->organization('OWN');
        [, , $otherDepartment] = $this->organization('OTHERDEPT');
        $registrar = $this->userWithPermissions('registrar', ['students.view', 'students.create'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $visibleStudent = $this->student($university, $department, [
            'full_name' => 'Department Registrar Student',
        ]);
        $hiddenStudent = $this->student(University::find($otherDepartment->university_id), $otherDepartment, [
            'full_name' => 'Other Department Student',
        ]);

        $this->actingAs($registrar)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Department Registrar Student')
            ->assertSee('Other Department Student');

        $this->actingAs($registrar)
            ->get(route('students.show', $visibleStudent))
            ->assertOk();

        $this->actingAs($registrar)
            ->get(route('students.show', $hiddenStudent))
            ->assertOk();
    }

    public function test_student_directory_requires_view_permission(): void
    {
        $user = $this->userWithPermissions('admission_officer', ['students.create']);

        $this->actingAs($user)->get(route('students.index'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('students.index').'"', false);
    }

    public function test_search_status_filter_and_classification_use_all_matching_students(): void
    {
        [$university, , $department] = $this->organization('LIST');
        $admin = $this->superAdmin();

        foreach (range(1, 16) as $number) {
            $this->student($university, $department, [
                'student_id' => 'LIST-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'full_name' => 'Directory Student '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'email' => "directory{$number}@example.com",
                'status' => $number === 16 ? 'Inactive' : 'Active',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('16 students')
            ->assertSee('16 records');

        $this->actingAs($admin)
            ->get(route('students.index', ['q' => 'directory16@example.com']))
            ->assertOk()
            ->assertSee('Directory Student 16')
            ->assertDontSee('Directory Student 01');

        $this->actingAs($admin)
            ->get(route('students.index', ['status' => 'Inactive']))
            ->assertOk()
            ->assertSee('Directory Student 16')
            ->assertDontSee('Directory Student 01');
    }

    public function test_student_account_is_securely_provisioned_and_kept_in_sync(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('SYNC');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('students.store'), $this->studentPayload($department, [
                'full_name' => 'Original Student',
                'email' => 'original.student@example.com',
            ]))
            ->assertRedirect(route('students.index'));

        $student = Student::where('email', 'original.student@example.com')->firstOrFail();
        $account = User::findOrFail($student->user_id);

        $this->assertTrue(Hash::check('TempPassword123', $account->password));
        $this->assertTrue($account->must_change_password);
        Notification::assertNothingSent();

        $this->actingAs($admin)
            ->put(route('students.update', $student), $this->studentPayload($department, [
                'full_name' => 'Updated Student',
                'email' => 'updated.student@example.com',
            ]))
            ->assertRedirect(route('students.edit', $student));

        $account->refresh();
        $this->assertSame('Updated Student', $account->name);
        $this->assertSame('updated.student@example.com', $account->email);
        $this->assertSame($university->id, $account->university_id);
        $this->assertSame($department->college_id, $account->college_id);
        $this->assertSame($department->id, $account->department_id);
    }

    public function test_student_stage_is_scoped_and_department_change_is_blocked_after_enrollment(): void
    {
        [$university, $college, $department] = $this->organization('PLACEMENT');
        $otherDepartment = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Transferred Department',
            'code' => 'TRANSFER',
        ]);
        $stage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 1',
            'sequence' => 1,
        ]);
        $otherStage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
            'name' => 'Stage 1',
            'sequence' => 1,
        ]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('students.store'), $this->studentPayload($department, [
                'email' => 'placed.student@example.com',
                'current_stage_id' => $stage->id,
            ]))
            ->assertRedirect(route('students.index'));

        $student = Student::where('email', 'placed.student@example.com')->firstOrFail();
        $this->assertSame($stage->id, $student->current_stage_id);

        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'PLC101', 'name' => 'Placement', 'credits' => 3]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'section_code' => 'A',
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('students.update', $student), $this->studentPayload($otherDepartment, [
                'full_name' => $student->full_name,
                'email' => $student->email,
                'current_stage_id' => $otherStage->id,
            ]))
            ->assertSessionHasErrors('department_id');

        $this->assertSame($department->id, $student->fresh()->department_id);
        $this->assertSame($stage->id, $student->fresh()->current_stage_id);
    }

    public function test_scoped_administrator_only_sees_and_assigns_students_inside_their_organization(): void
    {
        [$university, $college, $department] = $this->organization('SCOPED');
        [$otherUniversity, $otherCollege, $otherDepartment] = $this->organization('OUTSIDE');
        $admin = $this->userWithPermissions('university_administrator', ['students.view', 'students.create', 'students.update'], [
            'university_id' => $university->id,
        ]);
        $student = $this->student($university, $department, [
            'full_name' => 'Scoped Student',
            'email' => 'scoped.student@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('students.create'))
            ->assertOk()
            ->assertSeeInOrder(['University', 'College', 'Department'])
            ->assertSee($university->name)
            ->assertSee($college->name)
            ->assertSee($department->name)
            ->assertDontSee($otherUniversity->name)
            ->assertDontSee($otherCollege->name)
            ->assertDontSee($otherDepartment->name);

        $this->actingAs($admin)
            ->from(route('students.create'))
            ->post(route('students.store'), $this->studentPayload($otherDepartment, [
                'university_id' => $otherUniversity->id,
                'college_id' => $otherCollege->id,
                'email' => 'outside.create@example.com',
            ]))
            ->assertRedirect(route('students.create'))
            ->assertSessionHasErrors('department_id');

        $this->actingAs($admin)
            ->from(route('students.edit', $student))
            ->put(route('students.update', $student), $this->studentPayload($otherDepartment, [
                'university_id' => $otherUniversity->id,
                'college_id' => $otherCollege->id,
                'full_name' => $student->full_name,
                'email' => $student->email,
            ]))
            ->assertRedirect(route('students.edit', $student))
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('students', ['email' => 'outside.create@example.com']);
        $this->assertSame($department->id, $student->fresh()->department_id);
    }

    public function test_student_filters_and_archived_records_follow_organization_scope(): void
    {
        [$university, $college, $department] = $this->organization('ARCHSCOPE');
        [$otherUniversity, $otherCollege, $otherDepartment] = $this->organization('ARCHOTHER');
        $visible = $this->student($university, $department, ['full_name' => 'Visible Archived Student']);
        $hidden = $this->student($otherUniversity, $otherDepartment, ['full_name' => 'Hidden Archived Student']);
        $visible->delete();
        $hidden->delete();

        $admin = $this->userWithPermissions('college_administrator', ['students.view', 'students.archive'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
        ]);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Archived (1)')
            ->assertSee($college->name)
            ->assertDontSee($otherCollege->name);

        $this->actingAs($admin)
            ->get(route('students.archived'))
            ->assertOk()
            ->assertSee($visible->full_name)
            ->assertDontSee($hidden->full_name);

        $this->actingAs($admin)
            ->patch(route('students.restore', $hidden->id))
            ->assertNotFound();
    }

    public function test_student_document_download_hides_records_outside_organization_scope(): void
    {
        Storage::fake('local');
        [$university, $college] = $this->organization('DOCSCOPE');
        [$otherUniversity, , $otherDepartment] = $this->organization('DOCOTHER');
        $student = $this->student($otherUniversity, $otherDepartment);
        $document = StudentDocument::create([
            'student_id' => $student->id,
            'type' => 'Admission',
            'title' => 'Protected document',
            'file_path' => 'student-documents/protected.pdf',
            'original_name' => 'protected.pdf',
            'status' => 'Submitted',
        ]);
        Storage::disk('local')->put($document->file_path, 'protected content');
        $admin = $this->userWithPermissions('college_administrator', ['students.view'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
        ]);

        $this->actingAs($admin)
            ->get(route('student-documents.download', $document))
            ->assertNotFound();
    }

    public function test_student_admission_status_rejects_unknown_values(): void
    {
        [, , $department] = $this->organization('ADMISSION');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->from(route('students.create'))
            ->post(route('students.store'), $this->studentPayload($department, [
                'admission_status' => 'Unknown status',
            ]))
            ->assertRedirect(route('students.create'))
            ->assertSessionHasErrors('admission_status');
    }

    public function test_registrar_creates_student_login_account_when_adding_student(): void
    {
        Notification::fake();
        [$university, , $department] = $this->organization('REGUSER');
        $registrar = $this->userWithPermissions('registrar', ['students.create', 'students.view']);

        $this->actingAs($registrar)
            ->post(route('students.store'), $this->studentPayload($department, [
                'full_name' => 'Registrar Created Student',
                'email' => 'registrar.created@example.com',
                'password' => 'RegistrarTemp123',
                'password_confirmation' => 'RegistrarTemp123',
            ]))
            ->assertRedirect(route('students.index'));

        $student = Student::where('email', 'registrar.created@example.com')->firstOrFail();
        $account = User::findOrFail($student->user_id);

        $this->assertSame('Registrar Created Student', $account->name);
        $this->assertSame('registrar.created@example.com', $account->email);
        $this->assertSame($university->id, $account->university_id);
        $this->assertSame($department->id, $account->department_id);
        $this->assertTrue(Hash::check('RegistrarTemp123', $account->password));
        $this->assertTrue($account->must_change_password);
        $this->assertTrue($account->roles()->where('name', 'student')->exists());
        Notification::assertNothingSent();
    }

    public function test_student_with_temporary_password_must_change_it_after_login(): void
    {
        [$university, , $department] = $this->organization('FORCEPWD');
        $registrar = $this->userWithPermissions('registrar', ['students.create', 'students.view']);

        $this->actingAs($registrar)
            ->post(route('students.store'), $this->studentPayload($department, [
                'full_name' => 'Temporary Password Student',
                'email' => 'temporary.password@example.com',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
            ]));

        auth()->logout();

        $this->post(route('login'), [
            'email' => 'temporary.password@example.com',
            'password' => 'Temporary123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $studentAccount = User::where('email', 'temporary.password@example.com')->firstOrFail();

        $this->actingAs($studentAccount)
            ->get(route('student-portal'))
            ->assertRedirect(route('password.force-edit'));

        $this->actingAs($studentAccount)
            ->put(route('password.update'), [
                'current_password' => 'Temporary123',
                'password' => 'StudentOwnPassword123',
                'password_confirmation' => 'StudentOwnPassword123',
            ])
            ->assertRedirect(route('dashboard'));

        $studentAccount->refresh();
        $this->assertFalse($studentAccount->must_change_password);
        $this->assertTrue(Hash::check('StudentOwnPassword123', $studentAccount->password));
    }

    public function test_archiving_and_restoring_student_suspends_and_restores_student_login(): void
    {
        Notification::fake();
        [, , $department] = $this->organization('ARCH');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('students.store'), $this->studentPayload($department, [
                'full_name' => 'Archive Student',
                'email' => 'archive.student@example.com',
            ]));

        $student = Student::where('email', 'archive.student@example.com')->firstOrFail();
        $userId = $student->user_id;

        $this->actingAs($admin)
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->actingAs($admin)->get(route('students.archived'))->assertOk()->assertSee('Archive Student');

        $this->actingAs($admin)
            ->patch(route('students.restore', $student->id))
            ->assertRedirect(route('students.archived'));

        $this->assertDatabaseHas('students', ['id' => $student->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $userId, 'deleted_at' => null]);
        $this->assertTrue(User::findOrFail($userId)->roles()->where('name', 'student')->exists());
    }

    public function test_scoped_academic_admin_can_open_visible_student_transcript(): void
    {
        [$university, $college, $department] = $this->organization('TRN');
        $student = $this->student($university, $department);
        $admin = $this->userWithPermissions('college_administrator', ['students.view', 'marks.view'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
        ]);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee(route('transcripts.show', $student), false);
        $this->actingAs($admin)->get(route('transcripts.show', $student))->assertOk();
    }

    public function test_department_head_can_reset_password_for_own_department_student(): void
    {
        [$university, $college, $department] = $this->organization('PASS');
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $studentAccount = User::factory()->create([
            'name' => 'Password Student',
            'email' => 'password.student@example.com',
            'password' => 'OldPassword123',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $studentAccount->roles()->attach($studentRole);

        $student = $this->student($university, $department, [
            'user_id' => $studentAccount->id,
            'full_name' => 'Password Student',
            'email' => 'password.student@example.com',
        ]);

        $head = $this->userWithPermissions('department_administrator', ['students.view', 'students.update'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($head)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Reset student password')
            ->assertSee(route('students.reset-password', $student), false);

        $this->actingAs($head)
            ->post(route('students.reset-password', $student), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertTrue(Hash::check('NewPassword123', $studentAccount->fresh()->password));
    }

    public function test_department_head_cannot_reset_password_for_other_department_student(): void
    {
        [$university, $college, $department] = $this->organization('PWD1');
        [, , $otherDepartment] = $this->organization('PWD2');
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $studentAccount = User::factory()->create([
            'email' => 'other.password@example.com',
            'password' => 'OldPassword123',
            'university_id' => $otherDepartment->university_id,
            'department_id' => $otherDepartment->id,
        ]);
        $studentAccount->roles()->attach($studentRole);
        $student = $this->student(University::find($otherDepartment->university_id), $otherDepartment, [
            'user_id' => $studentAccount->id,
            'email' => 'other.password@example.com',
        ]);

        $head = $this->userWithPermissions('department_administrator', ['students.view', 'students.update'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($head)
            ->post(route('students.reset-password', $student), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertNotFound();

        $this->assertTrue(Hash::check('OldPassword123', $studentAccount->fresh()->password));
    }

    public function test_department_head_cannot_reset_student_profile_linked_to_non_student_account(): void
    {
        [$university, $college, $department] = $this->organization('MIX');
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $mixedAccount = User::factory()->create([
            'email' => 'mixed.account@example.com',
            'password' => 'OldPassword123',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $mixedAccount->roles()->attach([$studentRole->id, $teacherRole->id]);
        $student = $this->student($university, $department, [
            'user_id' => $mixedAccount->id,
            'email' => 'mixed.account@example.com',
        ]);

        $head = $this->userWithPermissions('department_administrator', ['students.view', 'students.update'], [
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($head)
            ->post(route('students.reset-password', $student), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('OldPassword123', $mixedAccount->fresh()->password));
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

    private function student(University $university, Department $department, array $attributes = []): Student
    {
        return Student::factory()->create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'status' => 'Active',
        ], $attributes));
    }

    private function studentPayload(Department $department, array $attributes = []): array
    {
        return array_merge([
            'full_name' => 'Directory Student',
            'student_id' => 'DIR-100',
            'email' => 'directory.student@example.com',
            'department_id' => $department->id,
            'status' => 'Active',
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ], $attributes);
    }
}
