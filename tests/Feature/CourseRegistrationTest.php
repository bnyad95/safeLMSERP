<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CourseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentUser(Student $student): User
    {
        $permission = Permission::create([
            'name' => 'courses.view',
            'display_name' => 'View courses',
            'description' => 'View courses',
        ]);
        $role = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student access',
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'name' => $student->full_name,
            'email' => $student->email,
        ]);
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
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-REG',
            'full_name' => 'Dr. Course Register',
            'email' => 'register@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'CS330',
            'name' => 'Distributed Systems',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'G1',
            'grade_level' => 'Stage 3',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-REG-1',
            'full_name' => 'Course Register Student',
            'email' => 'register-student@example.com',
            'status' => 'Active',
        ]);

        return compact('department', 'semester', 'course', 'section', 'student');
    }

    public function test_student_cannot_access_admin_enrollments_page(): void
    {
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);

        $this->actingAs($user)
            ->get(route('enrollments.index'))
            ->assertForbidden();
    }

    public function test_student_can_register_for_available_course_group(): void
    {
        Mail::fake();
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);

        $this->actingAs($user)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertSee('Course Registration')
            ->assertSee('Distributed Systems')
            ->assertSee('Register');

        $this->actingAs($user)
            ->post(route('course-registration.store'), [
                'course_section_id' => $setup['section']->id,
            ])
            ->assertRedirect(route('course-registration.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'enrolled',
            'notes' => 'Student course registration',
        ]);
        $notification = AppNotification::where('student_id', $setup['student']->id)
            ->where('type', 'course_registration')
            ->firstOrFail();
        $this->assertSame(route('class-stream.show', $setup['section']), $notification->action_url);
        Mail::assertSent(EnrollmentConfirmation::class, fn ($mail) => $mail->hasTo($setup['student']->email));

        $this->actingAs($user)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertSee('Open classroom')
            ->assertSee(route('class-stream.show', $setup['section']), false);
    }

    public function test_student_cannot_register_for_same_course_twice(): void
    {
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);

        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-07-10',
        ]);

        $this->actingAs($user)
            ->from(route('course-registration.index'))
            ->post(route('course-registration.store'), [
                'course_section_id' => $setup['section']->id,
            ])
            ->assertRedirect(route('course-registration.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Enrollment::count());
    }

    public function test_failed_student_can_register_same_course_in_new_year_as_retake(): void
    {
        Mail::fake();
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);

        $setup['section']->update(['status' => 'closed']);
        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'completed',
            'enrolled_at' => '2026-07-10',
        ]);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 42,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now()->subMonth(),
        ]);

        $nextSemester = Semester::create([
            'university_id' => $setup['student']->university_id,
            'name' => 'Fall',
            'academic_year' => '2027/2028',
        ]);
        $retakeSection = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $nextSemester->id,
            'section_code' => 'R1',
            'grade_level' => 'Stage 3',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertSee('Distributed Systems')
            ->assertSee('Retake')
            ->assertSee('Available because your previous published result was below the pass mark.');

        $this->actingAs($user)
            ->post(route('course-registration.store'), [
                'course_section_id' => $retakeSection->id,
            ])
            ->assertRedirect(route('course-registration.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $retakeSection->id,
            'status' => 'enrolled',
            'is_retake' => true,
            'retake_reason' => 'Failed course retake',
            'notes' => 'Student course registration - retake',
        ]);
    }

    public function test_passed_student_cannot_register_same_course_again_in_new_year(): void
    {
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);

        $setup['section']->update(['status' => 'closed']);
        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'completed',
            'enrolled_at' => '2026-07-10',
        ]);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 65,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now()->subMonth(),
        ]);

        $nextSemester = Semester::create([
            'university_id' => $setup['student']->university_id,
            'name' => 'Fall',
            'academic_year' => '2027/2028',
        ]);
        $retakeSection = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $nextSemester->id,
            'section_code' => 'P1',
            'grade_level' => 'Stage 3',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertDontSee('Distributed Systems')
            ->assertDontSee('Retake');

        $this->actingAs($user)
            ->post(route('course-registration.store'), [
                'course_section_id' => $retakeSection->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You already passed this course and cannot register for it again.');
    }

    public function test_student_cannot_register_for_other_department_course(): void
    {
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);
        $otherDepartment = Department::create([
            'university_id' => $setup['student']->university_id,
            'name' => 'Accounting',
        ]);
        $otherCourse = Course::create([
            'department_id' => $otherDepartment->id,
            'code' => 'ACC101',
            'name' => 'Accounting Basics',
            'credits' => 3,
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $setup['section']->semester_id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('course-registration.store'), [
                'course_section_id' => $otherSection->id,
            ])
            ->assertForbidden();
    }

    public function test_registration_page_filters_courses_and_hides_full_groups(): void
    {
        $setup = $this->makeAcademicSetup();
        $user = $this->makeStudentUser($setup['student']);
        $fullCourse = Course::create([
            'department_id' => $setup['department']->id,
            'semester_id' => $setup['section']->semester_id,
            'code' => 'CS-FULL',
            'name' => 'Full Capacity Course',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $fullSection = CourseSection::create([
            'course_id' => $fullCourse->id,
            'semester_id' => $setup['section']->semester_id,
            'section_code' => 'FULL',
            'grade_level' => 'Stage 3',
            'capacity' => 1,
            'status' => 'active',
        ]);
        $otherStudent = Student::create([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['department']->id,
            'student_id' => 'BND-REG-FULL',
            'full_name' => 'Full Seat Student',
            'email' => 'full-seat@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'course_section_id' => $fullSection->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('course-registration.index', ['q' => 'Distributed', 'grade_level' => 'Stage 3']))
            ->assertOk()
            ->assertSee('Distributed Systems')
            ->assertDontSee('Full Capacity Course');

        $this->actingAs($user)
            ->get(route('course-registration.index', ['q' => 'missing course']))
            ->assertOk()
            ->assertSee('No course groups match the current filters or have available seats.')
            ->assertDontSee('Distributed Systems');
    }

    public function test_inactive_student_cannot_register(): void
    {
        $setup = $this->makeAcademicSetup();
        $setup['student']->update(['status' => 'Inactive']);
        $user = $this->makeStudentUser($setup['student']);

        $this->actingAs($user)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertSee('Course registration is unavailable while your student record is inactive.')
            ->assertSee('disabled', false);

        $this->actingAs($user)
            ->post(route('course-registration.store'), ['course_section_id' => $setup['section']->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only active students can register for courses.');

        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
        ]);
    }
}
