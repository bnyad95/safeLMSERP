<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrefinalMarkTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private CourseSection $section;

    private Student $student;

    private Student $outsideStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $this->teacherUser = User::factory()->create(['email' => 'prefinal.teacher@example.com']);
        $this->teacherUser->roles()->attach($role);
        $university = University::create(['name' => 'Marks University', 'code' => 'MRK']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'PREF-T1',
            'full_name' => 'Pre-final Teacher',
            'email' => $this->teacherUser->email,
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'PREF101',
            'name' => 'Pre-final Course',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $this->student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'PREF-S1',
            'full_name' => 'Enrolled Student',
            'email' => 'enrolled.prefinal@example.com',
            'status' => 'Active',
        ]);
        $this->outsideStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'PREF-S2',
            'full_name' => 'Outside Student',
            'email' => 'outside.prefinal@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }

    public function test_teacher_can_activate_entry_mode_and_save_prefinal_marks(): void
    {
        $this->actingAs($this->teacherUser)
            ->get(route('teacher-dashboard', ['section_id' => $this->section->id, 'tab' => 'grades']))
            ->assertOk()
            ->assertSee('Enter pre-final marks')
            ->assertSee('Save pre-final marks');

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.prefinal-marks.store', $this->section), [
                'prefinal_marks' => [$this->student->id => 72.5],
            ])
            ->assertRedirect(route('teacher-dashboard', ['section_id' => $this->section->id, 'tab' => 'grades']));

        $this->assertDatabaseHas('marks', [
            'student_id' => $this->student->id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 72.5,
            'submission_status' => 'draft',
        ]);
    }

    public function test_teacher_cannot_enter_marks_for_student_outside_class(): void
    {
        $this->actingAs($this->teacherUser)
            ->from(route('teacher-dashboard', ['section_id' => $this->section->id, 'tab' => 'grades']))
            ->post(route('teacher.prefinal-marks.store', $this->section), [
                'prefinal_marks' => [$this->outsideStudent->id => 80],
            ])
            ->assertSessionHasErrors('prefinal_marks');

        $this->assertDatabaseMissing('marks', ['student_id' => $this->outsideStudent->id]);
    }

    public function test_submitted_marks_are_locked_from_prefinal_changes(): void
    {
        Mark::create([
            'student_id' => $this->student->id,
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 65,
            'final_mark' => 80,
            'status' => 'Draft',
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.prefinal-marks.store', $this->section), [
                'prefinal_marks' => [$this->student->id => 90],
            ])
            ->assertSessionHasErrors('prefinal_marks');

        $this->assertDatabaseHas('marks', ['student_id' => $this->student->id, 'prefinal_mark' => 65]);
    }

    public function test_prefinal_marks_are_locked_after_exam_committee_enters_first_trial(): void
    {
        Mark::create([
            'student_id' => $this->student->id,
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 20,
            'first_trial_final_exam' => 20,
            'final_exam' => 20,
            'final_mark' => 40,
            'status' => 'Draft',
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.prefinal-marks.store', $this->section), [
                'prefinal_marks' => [$this->student->id => 35],
            ])
            ->assertSessionHasErrors('prefinal_marks');

        $this->assertDatabaseHas('marks', ['student_id' => $this->student->id, 'prefinal_mark' => 20]);
    }
}
