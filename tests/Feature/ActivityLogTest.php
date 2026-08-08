<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use App\Services\MarkSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    private function makeAdministrator(): User
    {
        $role = Role::create([
            'name' => 'administrator',
            'display_name' => 'Administrator',
            'description' => 'General admin',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(array $attributes = []): Student
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);

        return Student::create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-5001',
            'full_name' => 'Log Test Student',
            'email' => 'log.student@example.com',
            'status' => 'Active',
        ], $attributes));
    }

    private function makeAcademicSetup(): array
    {
        $university = University::create(['name' => 'Log University', 'code' => 'LOG']);
        $college = College::create(['university_id' => $university->id, 'name' => 'College of Science', 'code' => 'SCI']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-20',
        ]);
        $stage = Stage::create(['university_id' => $university->id, 'department_id' => $department->id, 'name' => 'Stage 1', 'sequence' => 1]);
        $course = Course::create(['department_id' => $department->id, 'semester_id' => $semester->id, 'code' => 'CS101', 'name' => 'Intro to CS', 'credits' => 3, 'semester' => 'Fall 2026']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-LOG-1',
            'full_name' => 'Log Teacher',
            'email' => 'log.teacher@example.com',
            'status' => 'Active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'current_stage_id' => $stage->id,
            'student_id' => 'LOG-1',
            'full_name' => 'Log Student',
            'email' => 'log.enroll@example.com',
            'status' => 'Active',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
            'status' => 'active',
        ]);

        return compact('university', 'college', 'department', 'semester', 'stage', 'course', 'teacher', 'student', 'section');
    }

    public function test_super_administrator_can_view_the_activity_log_page(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('activity-log'))
            ->assertOk()
            ->assertSee('Activity Log');
    }

    public function test_non_super_administrator_cannot_view_the_activity_log_page(): void
    {
        $administrator = $this->makeAdministrator();

        $this->actingAs($administrator)
            ->get(route('activity-log'))
            ->assertForbidden();
    }

    public function test_filtering_by_log_name_narrows_results(): void
    {
        $admin = $this->makeSuperAdmin();

        ActivityLog::create([
            'log_name' => 'finance_transaction',
            'description' => 'invoice_created',
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'properties' => ['note' => 'finance event marker'],
        ]);
        ActivityLog::create([
            'log_name' => 'enrollment',
            'description' => 'enrolled',
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'properties' => ['note' => 'enrollment event marker'],
        ]);

        $this->actingAs($admin)
            ->get(route('activity-log', ['log_name' => 'finance_transaction']))
            ->assertOk()
            ->assertSee('Invoice created')
            ->assertDontSee('Enrolled');
    }

    public function test_creating_a_finance_transaction_produces_an_activity_log_entry(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->post('/finance/transactions', [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '1250000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'INV-LOG-001',
                'academic_year' => '2026/2027',
                'transaction_date' => '2026-07-09',
                'due_date' => '2026-08-09',
                'notes' => 'Fall tuition',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $log = ActivityLog::where('log_name', 'finance_transaction')->where('description', 'invoice_created')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertIsArray($log->properties);
        $this->assertSame($student->id, $log->properties['student_id']);
    }

    public function test_enrolling_and_dropping_a_student_produces_activity_log_entries(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeAcademicSetup();

        $this->actingAs($admin)
            ->post(route('enrollments.store'), [
                'student_id' => $setup['student']->id,
                'course_section_id' => $setup['section']->id,
                'enrolled_at' => '2026-09-01',
            ])
            ->assertRedirect(route('enrollments.index'));

        $enrolledLog = ActivityLog::where('log_name', 'enrollment')->where('description', 'enrolled')->first();
        $this->assertNotNull($enrolledLog);
        $this->assertIsArray($enrolledLog->properties);
        $this->assertSame($setup['student']->id, $enrolledLog->properties['student_id']);

        $enrollment = Enrollment::where('student_id', $setup['student']->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('enrollments.drop', $enrollment), ['drop_reason' => 'Changed group']);

        $droppedLog = ActivityLog::where('log_name', 'enrollment')->where('description', 'dropped')->first();
        $this->assertNotNull($droppedLog);
        $this->assertSame($setup['student']->id, $droppedLog->properties['student_id']);
    }

    public function test_saving_a_tuition_rate_produces_an_activity_log_entry_and_avoids_duplicate_on_unchanged_resave(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Rate University', 'code' => 'RATE']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Rate Department']);
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);

        $payload = [
            'rates' => [
                $department->id => [
                    $academicYear->id => ['IQD' => '75000'],
                ],
            ],
        ];

        $this->actingAs($admin)->post(route('bologna-definition.tuition-rates.store'), $payload);

        $this->assertSame(1, ActivityLog::where('log_name', 'tuition_rate')->count());

        // Resaving the same value must not create a duplicate log entry.
        $this->actingAs($admin)->post(route('bologna-definition.tuition-rates.store'), $payload);

        $this->assertSame(1, ActivityLog::where('log_name', 'tuition_rate')->count());
    }

    public function test_mark_submission_service_stores_properties_as_a_real_array_not_double_encoded_json(): void
    {
        $setup = $this->makeAcademicSetup();
        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);
        $mark = Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 88,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);

        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        app(MarkSubmissionService::class)->submitMarks([$mark->id]);

        $log = ActivityLog::where('log_name', 'mark_submission')->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->properties);
        $this->assertArrayHasKey('mark_id', $log->properties);
        $this->assertSame($mark->id, $log->properties['mark_id']);
    }
}
