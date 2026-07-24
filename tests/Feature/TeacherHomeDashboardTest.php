<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherHomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_home_dashboard_is_scoped_to_assigned_classes(): void
    {
        // Freeze the dashboard on Monday in the application timezone.
        $this->travelTo('2026-07-13 01:30:00');

        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $teacherUser = User::factory()->create(['name' => 'Main Teacher', 'email' => 'main.teacher@example.com']);
        $teacherUser->roles()->attach($teacherRole);
        $studentUser = User::factory()->create(['name' => 'Dashboard Student', 'email' => 'dashboard.student@example.com']);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'THD-1',
            'full_name' => 'Main Teacher',
            'email' => $teacherUser->email,
            'status' => 'Active',
        ]);
        $otherTeacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'THD-2',
            'full_name' => 'Other Teacher',
            'email' => 'other.teacher@example.com',
            'status' => 'Active',
        ]);

        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'OWN101',
            'name' => 'Assigned Dashboard Class',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $otherCourse = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'OTHER101',
            'name' => 'Unassigned Private Class',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $semester->id,
            'teacher_id' => $otherTeacher->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'THD-ST-1',
            'full_name' => 'Dashboard Student',
            'email' => $studentUser->email,
            'status' => 'Active',
        ]);
        $otherStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'THD-ST-2',
            'full_name' => 'Hidden Student',
            'email' => 'hidden.student@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create(['student_id' => $student->id, 'course_section_id' => $section->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        Enrollment::create(['student_id' => $otherStudent->id, 'course_section_id' => $otherSection->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        Timetable::create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => 'Teacher Room 12',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        Timetable::create([
            'course_id' => $otherCourse->id,
            'course_section_id' => $otherSection->id,
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'room_number' => 'Hidden Room 99',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $assessment = AssessmentItem::create([
            'course_section_id' => $section->id,
            'created_by' => $teacherUser->id,
            'title' => 'Dashboard Assignment',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 10,
            'due_at' => now()->addDays(3),
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        $otherAssessment = AssessmentItem::create([
            'course_section_id' => $otherSection->id,
            'title' => 'Hidden Assignment',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 10,
            'due_at' => now()->addDays(3),
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        AssessmentSubmission::create([
            'assessment_item_id' => $assessment->id,
            'student_id' => $student->id,
            'content' => 'My submitted answer',
            'submitted_at' => now()->subHour(),
            'status' => 'submitted',
        ]);
        AssessmentSubmission::create([
            'assessment_item_id' => $otherAssessment->id,
            'student_id' => $otherStudent->id,
            'content' => 'Hidden answer',
            'submitted_at' => now()->subHour(),
            'status' => 'submitted',
        ]);

        ClassMessage::create([
            'course_section_id' => $section->id,
            'sender_id' => $studentUser->id,
            'recipient_id' => $teacherUser->id,
            'body' => 'Please review my work',
        ]);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => now()->subDay(), 'status' => 'absent']);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => now()->subDays(2), 'status' => 'present']);
        AppNotification::create(['user_id' => $teacherUser->id, 'type' => 'assessment', 'severity' => 'info', 'title' => 'New submission', 'body' => 'A student submitted work.']);

        $this->actingAs($teacherUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teacher Dashboard')
            ->assertSee('Monday, July 13')
            ->assertDontSee('My classes')
            ->assertSee('Assigned Dashboard Class')
            ->assertSee('Teacher Room 12')
            ->assertSee('Dashboard Assignment')
            ->assertSee('Dashboard Student')
            ->assertSee('Please review my work')
            ->assertSee('50.0%')
            ->assertDontSee('My timetable')
            ->assertDontSee('ERP Overview')
            ->assertDontSee('Academic Structure')
            ->assertDontSee('Unassigned Private Class')
            ->assertDontSee('Hidden Room 99')
            ->assertDontSee('Hidden Assignment')
            ->assertDontSee('Hidden Student');
    }

    public function test_super_administrator_keeps_the_erp_dashboard(): void
    {
        $role = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ERP Overview')
            ->assertSee('Academic Structure')
            ->assertDontSee('Teacher Dashboard');
    }
}
