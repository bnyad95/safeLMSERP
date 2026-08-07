<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_permissions_do_not_open_global_finance_or_attendance(): void
    {
        $role = $this->roleWithPermissions('student', ['finance.view', 'attendance.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('finance'))->assertForbidden();
        $this->actingAs($user)->get(route('attendance'))->assertForbidden();
    }

    public function test_department_administrator_only_sees_assigned_department_students(): void
    {
        [$firstUniversity, $firstCollege, $firstDepartment] = $this->organization('ONE');
        [, , $secondDepartment] = $this->organization('TWO');

        $visibleStudent = Student::factory()->create([
            'university_id' => $firstUniversity->id,
            'department_id' => $firstDepartment->id,
            'full_name' => 'Visible Department Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $secondDepartment->university_id,
            'department_id' => $secondDepartment->id,
            'full_name' => 'Hidden Department Student',
        ]);

        $role = $this->roleWithPermissions('department_administrator', ['students.view', 'students.update']);
        $user = User::factory()->create([
            'university_id' => $firstUniversity->id,
            'college_id' => $firstCollege->id,
            'department_id' => $firstDepartment->id,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee($visibleStudent->full_name)
            ->assertDontSee($hiddenStudent->full_name);

        $this->actingAs($user)
            ->get(route('students.edit', $hiddenStudent->id))
            ->assertNotFound();
    }

    public function test_unassigned_organization_administrator_sees_no_scoped_records(): void
    {
        [$university, , $department] = $this->organization('NONE');
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'full_name' => 'Protected Student',
        ]);

        $role = $this->roleWithPermissions('department_administrator', ['students.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertOk()
            ->assertDontSee($student->full_name);
    }

    public function test_university_administrator_scope_applies_to_courses_and_finance(): void
    {
        [$firstUniversity, , $firstDepartment] = $this->organization('FIN1');
        [$secondUniversity, , $secondDepartment] = $this->organization('FIN2');
        $firstStudent = Student::factory()->create([
            'university_id' => $firstUniversity->id,
            'department_id' => $firstDepartment->id,
        ]);
        $secondStudent = Student::factory()->create([
            'university_id' => $secondUniversity->id,
            'department_id' => $secondDepartment->id,
        ]);
        $firstCourse = Course::factory()->create(['department_id' => $firstDepartment->id]);
        $secondCourse = Course::factory()->create(['department_id' => $secondDepartment->id]);
        $firstTransaction = FinanceTransaction::create([
            'student_id' => $firstStudent->id,
            'type' => 'invoice',
            'amount' => 100,
            'transaction_date' => today(),
        ]);
        $secondTransaction = FinanceTransaction::create([
            'student_id' => $secondStudent->id,
            'type' => 'invoice',
            'amount' => 200,
            'transaction_date' => today(),
        ]);

        $role = $this->roleWithPermissions('university_administrator', ['courses.view', 'finance.view']);
        $user = User::factory()->create(['university_id' => $firstUniversity->id]);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $this->assertSame([$firstCourse->id], Course::pluck('id')->all());
        $this->assertSame([$firstTransaction->id], FinanceTransaction::pluck('id')->all());
        $this->assertNotContains($secondCourse->id, Course::pluck('id')->all());
        $this->assertNotContains($secondTransaction->id, FinanceTransaction::pluck('id')->all());
    }

    public function test_examination_administrator_can_open_mark_queue_with_review_permission(): void
    {
        $role = $this->roleWithPermissions('examination_administrator', ['marks.review']);
        [$university] = $this->organization('EXOPEN');
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('marks.submission-queue'))
            ->assertOk()
            ->assertSee('Mark Submission Queue');
    }

    public function test_assign_teacher_permission_does_not_manage_courses_enrollments_or_timetables(): void
    {
        [, , $department] = $this->organization('PERM');
        $course = Course::factory()->create(['department_id' => $department->id]);
        $role = $this->roleWithPermissions('administrator', ['courses.assign_teacher']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('course-records.index'))->assertForbidden();
        $this->actingAs($user)->get(route('course-records.edit', $course))->assertForbidden();
        $this->actingAs($user)->get(route('enrollments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('timetables.index'))->assertForbidden();
    }

    public function test_scoped_administrator_cannot_write_academic_records_to_another_organization(): void
    {
        [$firstUniversity, $firstCollege, $firstDepartment] = $this->organization('WRITE1');
        [$secondUniversity, $secondCollege, $secondDepartment] = $this->organization('WRITE2');
        $semester = Semester::create([
            'university_id' => $secondUniversity->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $course = Course::factory()->create(['department_id' => $secondDepartment->id]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $room = Classroom::create(['name' => 'Shared Room', 'capacity' => 30]);

        $role = $this->roleWithPermissions('department_administrator', [
            'courses.create',
            'teachers.create',
            'enrollments.manage',
            'timetable.manage',
        ]);
        $user = User::factory()->create([
            'university_id' => $firstUniversity->id,
            'college_id' => $firstCollege->id,
            'department_id' => $firstDepartment->id,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->post(route('course-records.store'), [
            'name' => 'Unauthorized Course',
            'code' => 'OUT-101',
            'college_id' => $secondCollege->id,
            'department_id' => $secondDepartment->id,
            'credits' => 3,
        ])->assertNotFound();

        $this->actingAs($user)->post(route('teachers.store'), [
            'full_name' => 'Outside Teacher',
            'staff_id' => 'OUT-T1',
            'email' => 'outside-teacher@example.com',
            'password' => 'ScopedTemp@123',
            'password_confirmation' => 'ScopedTemp@123',
            'university_id' => $secondUniversity->id,
            'college_id' => $secondCollege->id,
            'department_id' => $secondDepartment->id,
            'status' => 'Active',
        ])->assertNotFound();

        $this->actingAs($user)->post(route('timetables.store'), [
            'department_id' => $secondDepartment->id,
            'grade_level' => 'Stage 1',
            'course_section_id' => $section->id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'type' => 'lecture',
            'status' => 'scheduled',
        ])->assertNotFound();

        $this->assertDatabaseMissing('courses', ['code' => 'OUT-101']);
        $this->assertDatabaseMissing('teachers', ['staff_id' => 'OUT-T1']);
        $this->assertDatabaseMissing('timetables', ['course_section_id' => $section->id]);
    }

    public function test_registrar_can_manage_enrollments_with_dedicated_permission(): void
    {
        [$university, , $department] = $this->organization('REG');
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $stage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 1',
            'sequence' => 1,
        ]);
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'current_stage_id' => $stage->id,
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'section_code' => 'R1',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $role = $this->roleWithPermissions('registrar', ['courses.view', 'enrollments.view', 'enrollments.manage', 'timetable.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('enrollments.index'))->assertOk();
        $this->actingAs($user)->post(route('enrollments.store'), [
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'enrolled_at' => '2026-09-01',
        ])->assertRedirect(route('enrollments.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_registrar_does_not_receive_or_access_exams(): void
    {
        $role = $this->roleWithPermissions('registrar', ['reports.academic']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('exams'), false);
        $this->actingAs($user)->get(route('exams'))->assertForbidden();
    }

    public function test_examination_administrator_sees_mark_queue_action_from_exams(): void
    {
        $role = $this->roleWithPermissions('examination_administrator', ['marks.view', 'marks.review']);
        [$university] = $this->organization('EXLINK');
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('exams'))
            ->assertOk()
            ->assertSee(route('marks.submission-queue'), false);
    }

    public function test_review_only_mark_queue_hides_approve_reject_and_publish_actions(): void
    {
        $role = $this->roleWithPermissions('examination_committee', ['marks.review']);
        [$university] = $this->organization('EXREVIEW');
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('marks.submission-queue'))
            ->assertOk()
            ->assertDontSee(route('marks.approve'), false)
            ->assertDontSee(route('marks.reject'), false)
            ->assertDontSee(route('marks.publish'), false);
    }

    public function test_mark_queue_uses_independent_pagination_parameters(): void
    {
        [$university, , $department] = $this->organization('PAGES');
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);

        Mark::factory()->count(21)->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'submission_status' => 'submitted',
        ]);
        Mark::factory()->count(21)->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $role = $this->roleWithPermissions('examination_administrator', ['marks.review', 'marks.approve', 'marks.publish']);
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('marks.submission-queue'))
            ->assertOk()
            ->assertSee('pending_page=2', false)
            ->assertSee('approved_page=2', false);
    }

    public function test_scoped_examination_user_does_not_see_under_review_marks_outside_department(): void
    {
        [$firstUniversity, $firstCollege, $firstDepartment] = $this->organization('MQ1');
        [$secondUniversity, , $secondDepartment] = $this->organization('MQ2');
        $firstSemester = Semester::create(['university_id' => $firstUniversity->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $secondSemester = Semester::create(['university_id' => $secondUniversity->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $firstCourse = Course::factory()->create(['department_id' => $firstDepartment->id]);
        $secondCourse = Course::factory()->create(['department_id' => $secondDepartment->id]);
        $firstSection = CourseSection::create([
            'course_id' => $firstCourse->id,
            'semester_id' => $firstSemester->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
        ]);
        $secondSection = CourseSection::create([
            'course_id' => $secondCourse->id,
            'semester_id' => $secondSemester->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
        ]);
        $visibleStudent = Student::factory()->create([
            'university_id' => $firstUniversity->id,
            'department_id' => $firstDepartment->id,
            'full_name' => 'Visible Queue Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $secondUniversity->id,
            'department_id' => $secondDepartment->id,
            'full_name' => 'Hidden Queue Student',
        ]);

        Mark::factory()->create([
            'student_id' => $visibleStudent->id,
            'course_id' => $firstCourse->id,
            'course_section_id' => $firstSection->id,
            'submission_status' => 'submitted',
        ]);
        Mark::factory()->create([
            'student_id' => $hiddenStudent->id,
            'course_id' => $secondCourse->id,
            'course_section_id' => $secondSection->id,
            'submission_status' => 'under_review',
        ]);

        $examRole = $this->roleWithPermissions('examination_administrator', ['marks.review']);
        $user = User::factory()->create([
            'university_id' => $firstUniversity->id,
            'college_id' => $firstCollege->id,
            'department_id' => $firstDepartment->id,
        ]);
        $user->roles()->attach($examRole->id);

        $this->actingAs($user)
            ->get(route('marks.submission-queue'))
            ->assertOk()
            ->assertSee('Visible Queue Student')
            ->assertDontSee('Hidden Queue Student');
    }

    public function test_examination_committee_cannot_publish_marks(): void
    {
        [$university, , $department] = $this->organization('EXPUB');
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $mark = Mark::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $role = $this->roleWithPermissions('examination_committee', ['marks.view', 'marks.review', 'marks.approve', 'marks.request_change']);
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->post(route('marks.publish'), ['mark_ids' => [$mark->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'visibility_status' => 'draft',
        ]);
    }

    public function test_examination_committee_gets_focused_dashboard(): void
    {
        [$university] = $this->organization('EXDASH');
        $role = $this->roleWithPermissions('examination_committee', ['marks.view', 'marks.review', 'marks.approve', 'marks.request_change']);
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Examination Committee Dashboard')
            ->assertSee('Review Work')
            ->assertDontSee('Academic Structure');
    }

    public function test_seeded_examination_committee_role_reviews_but_does_not_publish(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'examination_committee')->firstOrFail();

        $this->assertSame('Examination Committee', $role->display_name);
        $this->assertTrue($role->permissions()->where('name', 'marks.approve')->exists());
        $this->assertTrue($role->permissions()->where('name', 'marks.request_change')->exists());
        $this->assertFalse($role->permissions()->where('name', 'marks.publish')->exists());
    }

    public function test_seeded_support_roles_match_their_workspaces(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $librarian = Role::where('name', 'librarian')->firstOrFail();
        $this->assertTrue($librarian->permissions()->where('name', 'lms.view')->exists());
        $this->assertTrue($librarian->permissions()->where('name', 'reports.academic')->exists());

        $parent = Role::where('name', 'parent_user')->firstOrFail();
        $this->assertTrue($parent->permissions()->where('name', 'attendance.view')->exists());
        $this->assertTrue($parent->permissions()->where('name', 'marks.view')->exists());
        $this->assertTrue($parent->permissions()->where('name', 'finance.view')->exists());

        $itSupport = Role::where('name', 'it_support')->firstOrFail();
        $this->assertTrue($itSupport->permissions()->where('name', 'users.reset_password')->exists());
        $this->assertFalse($itSupport->permissions()->where('name', 'students.view')->exists());

        $this->assertNull(Role::where('name', 'auditor')->first());
        $this->assertNull(Role::where('name', 'guest_user')->first());
    }

    public function test_academic_administrator_can_monitor_results_but_cannot_open_mark_queue(): void
    {
        $role = $this->roleWithPermissions('administrator', ['marks.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('exams'))
            ->assertOk()
            ->assertSee('Results Overview')
            ->assertDontSee(route('marks.submission-queue'), false);

        $this->actingAs($user)
            ->get(route('marks.submission-queue'))
            ->assertForbidden();
    }

    public function test_assessment_sidebar_visibility_matches_role_responsibilities(): void
    {
        $marksView = Permission::create(['name' => 'marks.view', 'display_name' => 'View marks']);
        $lmsView = Permission::create(['name' => 'lms.view', 'display_name' => 'View LMS']);

        foreach (['administrator', 'university_administrator', 'college_administrator', 'department_administrator'] as $roleName) {
            $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);
            $role->permissions()->attach($marksView);
            $user = User::factory()->create();
            $user->roles()->attach($role);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee(route('assessments.index'), false)
                ->assertSee('Assessment Analytics');
        }

        foreach (['lms_administrator', 'teaching_assistant'] as $roleName) {
            $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);
            $role->permissions()->attach($lmsView);
            $user = User::factory()->create();
            $user->roles()->attach($role);

            $response = $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee(route('assessments.index'), false)
                ->assertSee('Assessments')
                ->assertDontSee('Assessment Analytics');

            if ($roleName === 'teaching_assistant') {
                $response
                    ->assertSee(route('teacher-dashboard'), false)
                    ->assertSee('Teaching');

                $this->actingAs($user)
                    ->get(route('teacher-dashboard'))
                    ->assertOk()
                    ->assertSee('Colleges');
            }
        }

        $superRole = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $super = User::factory()->create();
        $super->roles()->attach($superRole);
        $this->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('assessments.index'), false)
            ->assertSee('Assessment Analytics');

        foreach (['examination_administrator', 'examination_committee'] as $roleName) {
            $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);
            $role->permissions()->attach($marksView);
            $user = User::factory()->create();
            $user->roles()->attach($role);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee(route('assessments.index'), false);
            $this->actingAs($user)->get(route('assessments.index'))->assertForbidden();
        }
    }

    public function test_finance_officer_sidebar_opens_finance_workspace(): void
    {
        $role = $this->roleWithPermissions('accountant', ['finance.view', 'finance.create_invoice']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('finance.dashboard'));

        $this->actingAs($user)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertSee(route('finance'), false)
            ->assertSee('Finance');
    }

    public function test_university_president_starts_from_institution_analytics(): void
    {
        $role = $this->roleWithPermissions('university_president', ['reports.academic']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('analytics.index'));
    }

    private function roleWithPermissions(string $name, array $permissionNames): Role
    {
        $role = Role::create([
            'name' => $name,
            'display_name' => str($name)->replace('_', ' ')->title(),
        ]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::create([
                'name' => $permissionName,
                'display_name' => str($permissionName)->replace('.', ' ')->title(),
            ]);
            $role->permissions()->attach($permission);
        }

        return $role;
    }

    private function organization(string $suffix): array
    {
        $university = University::create(['name' => "University {$suffix}", 'code' => "U{$suffix}"]);
        $college = College::create(['university_id' => $university->id, 'name' => "College {$suffix}", 'code' => "C{$suffix}"]);
        $department = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => "Department {$suffix}",
            'code' => "D{$suffix}",
        ]);

        return [$university, $college, $department];
    }
}
