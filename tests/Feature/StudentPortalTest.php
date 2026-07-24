<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_access_student_portal_and_see_own_data(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student role',
        ]);

        $user = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student@example.com',
        ]);
        $user->roles()->attach($studentRole->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'user_id' => $user->id,
            'student_id' => 'BND-9001',
            'full_name' => 'Student User',
            'email' => 'student-record@example.com',
            'status' => 'Active',
        ]);

        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'CS101',
            'name' => 'Introduction to Programming',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacherUser = User::factory()->create(['name' => 'Portal Teacher', 'email' => 'portal.teacher@example.com']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'PORTAL-T1',
            'full_name' => 'Portal Teacher',
            'email' => $teacherUser->email,
            'status' => 'Active',
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
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        AssessmentItem::create([
            'course_section_id' => $section->id,
            'title' => 'Portal Upcoming Work',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 10,
            'due_at' => now()->addDay(),
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        AssessmentItem::create([
            'course_section_id' => $section->id,
            'title' => 'Due Today Work',
            'type' => 'assignment',
            'max_score' => 10,
            'weight_percent' => 5,
            'due_at' => now()->addHour(),
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        ClassMessage::create([
            'course_section_id' => $section->id,
            'sender_id' => $teacherUser->id,
            'recipient_id' => $user->id,
            'body' => 'Unread portal message',
        ]);
        Timetable::create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => now('Asia/Baghdad')->format('l'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => 'Portal Room',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'final_mark' => 86,
            'status' => 'Published',
        ]);
        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'final_mark' => 42,
            'status' => 'Draft',
        ]);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-01', 'status' => 'present']);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-02', 'status' => 'late']);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-03', 'status' => 'absent']);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '500.00',
            'balance_after' => '500.00',
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-DASH-001',
            'reference' => 'Tuition',
            'transaction_date' => '2026-07-18',
            'due_date' => '2026-07-30',
        ]);

        $this->actingAs($user)
            ->get('/student-portal')
            ->assertOk()
            ->assertSee('Student Portal')
            ->assertSee('Today')
            ->assertSee('Introduction to Programming')
            ->assertSee('86')
            ->assertSee('66.7%')
            ->assertSee('1 absent / 3 records')
            ->assertSee('Finance Balance')
            ->assertSee('500.00 USD')
            ->assertSee('Next due Jul 30, 2026')
            ->assertSee('Portal Teacher')
            ->assertSee('Due Today Work')
            ->assertSee('Due Today')
            ->assertSee('Unread Messages')
            ->assertSee('Open work')
            ->assertSee('Unread')
            ->assertSee('1 today')
            ->assertSee('Finance')
            ->assertSee('Course Registration')
            ->assertSee('My Timetable')
            ->assertSee('Account Settings')
            ->assertSee('Enrolled Classes')
            ->assertSee('View Transcript')
            ->assertDontSee('Academic Profile')
            ->assertDontSee('BND-9001')
            ->assertDontSee('Final: 42')
            ->assertDontSee('Draft')
            ->assertDontSee('Department Courses')
            ->assertDontSee('My Profile')
            ->assertDontSee('My Assessments')
            ->assertDontSee('Search ERP...')
            ->assertDontSee(route('students.index'), false)
            ->assertDontSee(route('finance'), false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('student-portal'));

        $this->actingAs($user)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('My Timetable')
            ->assertDontSee('Schedule course sections into rooms and time slots with conflict checks.');
    }

    public function test_student_without_linked_profile_only_sees_profile_warning(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student role',
        ]);
        $user = User::factory()->create(['email' => 'unlinked.student@example.com']);
        $user->roles()->attach($studentRole->id);

        $this->actingAs($user)
            ->get('/student-portal')
            ->assertOk()
            ->assertSee('No student profile is linked')
            ->assertDontSee('Published Results')
            ->assertDontSee('Today')
            ->assertDontSee('Latest Results')
            ->assertDontSee('My Classes');
    }

    public function test_student_portal_hides_closed_year_enrollments_even_if_roster_row_is_still_enrolled(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student role',
        ]);
        $user = User::factory()->create([
            'name' => 'Closed Year Student',
            'email' => 'closed-year.student@example.com',
        ]);
        $user->roles()->attach($studentRole);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'user_id' => $user->id,
            'student_id' => 'BND-CLOSED-1',
            'full_name' => 'Closed Year Student',
            'email' => $user->email,
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'OLD101',
            'name' => 'Old Closed Math',
            'credits' => 3,
        ]);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Semester 1',
            'academic_year' => '2026/2027',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'closed',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        Attendance::create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'student_id' => $student->id,
            'date' => '2026-07-01',
            'status' => 'present',
        ]);
        AssessmentItem::create([
            'course_section_id' => $section->id,
            'title' => 'Old Open Work',
            'type' => 'assignment',
            'max_score' => 10,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('student-portal'))
            ->assertOk()
            ->assertSee('Enrolled Classes')
            ->assertSee('0')
            ->assertSee('You are not enrolled in a class yet.')
            ->assertSee('Attendance has not been recorded yet')
            ->assertDontSee('Old Closed Math')
            ->assertDontSee('Old Open Work')
            ->assertDontSee('100.0%');
    }

    public function test_student_can_view_only_own_finance_from_sidebar(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student role',
        ]);

        $user = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student.finance@example.com',
        ]);
        $user->roles()->attach($studentRole->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);
        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-FIN-1',
            'full_name' => 'Student Finance',
            'email' => $user->email,
            'status' => 'Active',
        ]);
        $otherStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-FIN-2',
            'full_name' => 'Other Finance',
            'email' => 'other.finance@example.com',
            'status' => 'Active',
        ]);

        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-STUDENT-001',
            'reference' => 'Tuition',
            'transaction_date' => '2026-07-18',
        ]);
        FinanceTransaction::create([
            'student_id' => $otherStudent->id,
            'type' => 'invoice',
            'amount' => '900',
            'balance_after' => '900',
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-OTHER-001',
            'reference' => 'Other tuition',
            'transaction_date' => '2026-07-18',
        ]);

        $this->actingAs($user)
            ->get(route('student.finance'))
            ->assertOk()
            ->assertSee('My Finance')
            ->assertSee('INV-STUDENT-001')
            ->assertSee('500,000.00 IQD')
            ->assertDontSee('INV-OTHER-001')
            ->assertDontSee('Other Finance');

        $this->actingAs($user)
            ->get(route('finance'))
            ->assertForbidden();
    }

    public function test_non_student_user_cannot_access_student_portal(): void
    {
        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Instructor',
            'description' => 'Teacher role',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($teacherRole->id);

        $this->actingAs($user)
            ->get('/student-portal')
            ->assertForbidden();
    }

    public function test_super_admin_cannot_access_student_portal(): void
    {
        $adminRole = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($adminRole->id);

        $this->actingAs($user)
            ->get('/student-portal')
            ->assertForbidden();
    }
}
