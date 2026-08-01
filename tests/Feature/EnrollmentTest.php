<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $role = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeAcademicSetup(): array
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-20',
        ]);
        $stage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 2',
            'sequence' => 2,
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'CS201',
            'name' => 'Data Structures',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-100',
            'full_name' => 'Dr. Rana Aziz',
            'email' => 'rana@example.com',
            'status' => 'Active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'S-100',
            'full_name' => 'Noor Student',
            'email' => 'noor@example.com',
            'status' => 'Active',
        ]);

        return compact('course', 'semester', 'stage', 'teacher', 'student');
    }

    private function makeSection(array $setup, string $group = 'A', array $overrides = []): CourseSection
    {
        return CourseSection::create(array_merge([
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $setup['stage']->id,
            'teacher_id' => $setup['teacher']->id,
            'section_code' => $group,
            'grade_level' => 'Stage 2',
            'capacity' => 2,
            'status' => 'active',
        ], $overrides));
    }

    public function test_admin_can_create_course_section_with_teacher_assignment(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();

        $this->actingAs($admin)
            ->post(route('course-sections.store'), [
                'course_id' => $setup['course']->id,
                'semester_id' => $setup['semester']->id,
                'stage_id' => $setup['stage']->id,
                'teacher_id' => $setup['teacher']->id,
                'section_code' => 'A',
                'grade_level' => 'Stage 2',
                'capacity' => 35,
                'status' => 'active',
                'notes' => 'Morning group',
            ])
            ->assertRedirect(route('module-offerings.index'));

        $this->assertDatabaseHas('course_sections', [
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $setup['stage']->id,
            'teacher_id' => $setup['teacher']->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 2',
            'capacity' => 35,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_enroll_student_into_section(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'teacher_id' => $setup['teacher']->id,
            'section_code' => 'A',
            'capacity' => 2,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-01',
            ])
            ->assertRedirect(route('enrollments.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_duplicate_active_enrollment_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'teacher_id' => $setup['teacher']->id,
            'section_code' => 'A',
            'capacity' => 2,
            'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->from(route('enrollments.index'))
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-02',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Enrollment::count());
    }

    public function test_section_capacity_is_enforced(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'teacher_id' => $setup['teacher']->id,
            'section_code' => 'A',
            'capacity' => 1,
            'status' => 'active',
        ]);
        $secondStudent = Student::create([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['student']->department_id,
            'student_id' => 'S-101',
            'full_name' => 'Second Student',
            'email' => 'second@example.com',
            'status' => 'Active',
        ]);

        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->from(route('enrollments.index'))
            ->post(route('enrollments.store'), [
                'student_id' => $secondStudent->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-02',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Enrollment::count());
    }

    public function test_admin_can_open_directory_and_section_profile(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = $this->makeSection($setup);

        $this->actingAs($admin)
            ->get(route('module-offerings.index'))
            ->assertOk()
            ->assertSee('Module Offerings')
            ->assertSee('CS201')
            ->assertSee('Program Semester 3');

        $this->actingAs($admin)
            ->get(route('course-sections.show', $section))
            ->assertOk()
            ->assertSee('Roster')
            ->assertSee('Waitlist')
            ->assertSee('History')
            ->assertSee('Program Semester 3');
    }

    public function test_module_offering_calculates_program_semester_eight_and_keeps_summer_separate(): void
    {
        $setup = $this->makeAcademicSetup();
        $stageFour = Stage::create([
            'university_id' => $setup['semester']->university_id,
            'department_id' => $setup['course']->department_id,
            'name' => 'Stage 4',
            'sequence' => 4,
        ]);
        $secondSemester = Semester::create([
            'university_id' => $setup['semester']->university_id,
            'name' => 'Semester 2',
            'academic_year' => '2026/2027',
            'term_type' => 'regular',
            'sequence' => 2,
            'start_date' => '2027-01-21',
            'end_date' => '2027-06-30',
        ]);
        $eighthSemesterSection = $this->makeSection($setup, 'B', [
            'semester_id' => $secondSemester->id,
            'stage_id' => $stageFour->id,
        ]);

        $this->assertSame(8, $eighthSemesterSection->programSemesterNumber());
        $this->assertSame('Program Semester 8', $eighthSemesterSection->programSemesterLabel());

        $summer = Semester::create([
            'university_id' => $setup['semester']->university_id,
            'name' => 'Summer Semester',
            'academic_year' => '2026/2027',
            'term_type' => 'summer',
            'sequence' => 3,
            'start_date' => '2027-07-01',
            'end_date' => '2027-08-20',
        ]);
        $section = $this->makeSection($setup, 'S', [
            'semester_id' => $summer->id,
            'stage_id' => $stageFour->id,
        ]);

        $this->assertNull($section->programSemesterNumber());
        $this->assertSame('Summer Semester', $section->programSemesterLabel());
    }

    public function test_inactive_student_or_closed_section_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $setup['student']->update(['status' => 'Inactive']);
        $section = $this->makeSection($setup);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-01',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $setup['student']->update(['status' => 'Active']);
        $section->update(['status' => 'closed']);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-01',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_duplicate_course_semester_group_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $sectionA = $this->makeSection($setup, 'A');
        $sectionB = $this->makeSection($setup, 'B');

        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $sectionA->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $sectionB->id,
                'enrolled_at' => '2026-09-02',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Enrollment::where('student_id', $setup['student']->id)->count());
    }

    public function test_timetable_conflict_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $existingSection = $this->makeSection($setup, 'A');
        $secondCourse = Course::create([
            'department_id' => $setup['course']->department_id,
            'code' => 'CS301',
            'name' => 'Algorithms',
            'credits' => 3,
        ]);
        $targetSection = $this->makeSection($setup, 'B', ['course_id' => $secondCourse->id]);

        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $existingSection->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);
        Timetable::create([
            'course_id' => $setup['course']->id,
            'course_section_id' => $existingSection->id,
            'teacher_id' => $setup['teacher']->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        Timetable::create([
            'course_id' => $secondCourse->id,
            'course_section_id' => $targetSection->id,
            'teacher_id' => $setup['teacher']->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $targetSection->id,
                'enrolled_at' => '2026-09-02',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $targetSection->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_waitlist_student_can_be_promoted_after_capacity_opens(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = $this->makeSection($setup, 'A', ['capacity' => 1]);
        $secondStudent = Student::create([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['student']->department_id,
            'student_id' => 'S-102',
            'full_name' => 'Waitlist Student',
            'email' => 'waitlist@example.com',
            'status' => 'Active',
        ]);
        $firstEnrollment = Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $secondStudent->id,
                'course_section_id' => $section->id,
                'enrolled_at' => '2026-09-02',
                'action' => 'waitlist',
            ])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('success');

        $waitlist = Enrollment::where('student_id', $secondStudent->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('enrollments.drop', $firstEnrollment), ['drop_reason' => 'Changed group'])
            ->assertRedirect(route('course-sections.show', $section));

        $this->actingAs($admin)
            ->patch(route('enrollments.promote', $waitlist))
            ->assertRedirect(route('course-sections.show', $section))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'id' => $waitlist->id,
            'status' => 'enrolled',
        ]);
        $this->assertDatabaseHas('enrollment_events', [
            'enrollment_id' => $waitlist->id,
            'action' => 'promoted',
        ]);
    }

    public function test_student_can_be_transferred_between_groups_with_history(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $sectionA = $this->makeSection($setup, 'A');
        $sectionB = $this->makeSection($setup, 'B');
        $enrollment = Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $sectionA->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->patch(route('enrollments.transfer', $enrollment), [
                'target_section_id' => $sectionB->id,
                'reason' => 'Schedule change',
            ])
            ->assertRedirect(route('course-sections.show', $sectionA))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'dropped',
            'drop_reason' => 'Transferred: Schedule change',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $sectionB->id,
            'status' => 'enrolled',
            'transferred_from_id' => $enrollment->id,
        ]);
        $this->assertTrue(EnrollmentEvent::where('action', 'transferred_in')->exists());
    }

    public function test_closed_empty_section_can_be_archived_and_restored(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = $this->makeSection($setup, 'A', ['status' => 'closed']);

        $this->actingAs($admin)
            ->delete(route('course-sections.destroy', $section))
            ->assertRedirect(route('module-offerings.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('course_sections', ['id' => $section->id]);

        $this->actingAs($admin)
            ->patch(route('course-sections.restore', $section->id))
            ->assertRedirect(route('course-sections.archived'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('course_sections', [
            'id' => $section->id,
            'deleted_at' => null,
        ]);
    }

    public function test_department_administrator_only_sees_modules_in_their_department(): void
    {
        $university = University::create(['name' => 'Scoped University', 'code' => 'SU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Scoped College', 'code' => 'SC']);
        $ownDepartment = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Own Department', 'code' => 'OWN']);
        $otherDepartment = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Other Department', 'code' => 'OTHER']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2027/2028']);
        $ownCourse = Course::create(['department_id' => $ownDepartment->id, 'code' => 'OWN101', 'name' => 'Visible Module', 'credits' => 3]);
        $otherCourse = Course::create(['department_id' => $otherDepartment->id, 'code' => 'OTHER101', 'name' => 'Hidden Module', 'credits' => 3]);
        $ownSection = CourseSection::create(['course_id' => $ownCourse->id, 'semester_id' => $semester->id, 'section_code' => 'A']);
        $otherSection = CourseSection::create(['course_id' => $otherCourse->id, 'semester_id' => $semester->id, 'section_code' => 'B']);

        $permission = Permission::create(['name' => 'enrollments.view', 'display_name' => 'View enrollments']);
        $role = Role::create(['name' => 'department_administrator', 'display_name' => 'Department Administrator']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $ownDepartment->id,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('module-offerings.index'))
            ->assertOk()
            ->assertSee($ownCourse->name)
            ->assertDontSee($otherCourse->name);

        $this->actingAs($user)->get(route('course-sections.show', $ownSection))->assertOk();
        $this->actingAs($user)->get(route('course-sections.show', $otherSection))->assertNotFound();
    }

    public function test_enrollment_workspace_lists_student_registrations_separately_from_module_offerings(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();
        $section = $this->makeSection($setup);
        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('Student Registrations')
            ->assertSee($setup['student']->full_name)
            ->assertSee($setup['course']->name)
            ->assertSee('Open roster');

        $this->actingAs($admin)
            ->get(route('module-offerings.index'))
            ->assertOk()
            ->assertSee('Add Module')
            ->assertSee($setup['course']->name);
    }
}
