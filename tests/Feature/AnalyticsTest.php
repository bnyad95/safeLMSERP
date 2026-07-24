<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnalyticsUser(): User
    {
        $role = Role::create([
            'name' => 'administrator',
            'display_name' => 'Academic Administrator',
            'description' => 'Academic leadership',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudentUser(): User
    {
        $role = Role::create([
            'name' => 'student',
            'display_name' => 'Student',
            'description' => 'Student role',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_authorized_user_can_view_analytics_signals(): void
    {
        $user = $this->makeAnalyticsUser();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science']);
        $user->update([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'AN-1001',
            'full_name' => 'Analytics Student',
            'email' => 'analytics@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'CS450',
            'name' => 'Data Mining',
            'credits' => 3,
        ]);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026']);
        $section = CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'section_code' => 'A', 'grade_level' => 'Stage 1']);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-01', 'status' => 'absent']);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-02', 'status' => 'absent']);
        Attendance::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'student_id' => $student->id, 'date' => '2026-07-03', 'status' => 'present']);

        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 86,
            'status' => 'Published',
            'visibility_status' => 'published',
        ]);

        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1200000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'overdue',
            'transaction_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'academic_year' => '2026',
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '300',
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'open',
            'transaction_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'academic_year' => '2026',
        ]);

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Institution Analytics')
            ->assertSee('Academic')
            ->assertSee('Attendance')
            ->assertSee('Courses')
            ->assertSee('GPA Trend')
            ->assertSee('Fall 2026')
            ->assertDontSee('Finance')
            ->assertDontSee('1,200,000.00 IQD / 300.00 USD')
            ->assertDontSee('Unpaid Balances')
            ->assertDontSee('Course Performance');

        $this->actingAs($user)
            ->get(route('analytics.index', ['tab' => 'attendance', 'college_id' => $college->id, 'department_id' => $department->id, 'stage' => 'Stage 1', 'semester_id' => $semester->id, 'academic_year' => '2026']))
            ->assertOk()
            ->assertSee('Attendance Risk')
            ->assertSee('Analytics Student')
            ->assertDontSee('GPA Trend');

        $this->actingAs($user)
            ->get(route('analytics.index', ['tab' => 'finance', 'academic_year' => '2026']))
            ->assertForbidden();

        $financePermission = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $user->permissionOverrides()->attach($financePermission, ['effect' => 'grant']);

        $this->actingAs($user)
            ->get(route('analytics.index', ['tab' => 'finance', 'academic_year' => '2026']))
            ->assertOk()
            ->assertSee('Unpaid Balances')
            ->assertSee('1,200,000.00 IQD')
            ->assertSee('300.00 USD');

        $this->actingAs($user)
            ->get(route('analytics.index', ['tab' => 'courses', 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Course Performance')
            ->assertSee('Data Mining');
    }

    public function test_user_without_reporting_permission_cannot_view_analytics(): void
    {
        $user = $this->makeStudentUser();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_global_analytics_even_with_academic_permissions(): void
    {
        $permission = Permission::create(['name' => 'marks.view', 'display_name' => 'View marks']);
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $role->permissions()->attach($permission);
        $teacher = User::factory()->create();
        $teacher->roles()->attach($role);

        $this->actingAs($teacher)
            ->get(route('analytics.index'))
            ->assertForbidden();
    }
}
