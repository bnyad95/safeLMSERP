<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AssessmentItem;
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
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoleUser(string $roleName, array $permissions = [], array $attributes = []): User
    {
        $role = Role::create([
            'name' => $roleName,
            'display_name' => str_replace('_', ' ', ucfirst($roleName)),
            'description' => 'Test role',
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'description' => $permissionName]
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create($attributes);
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
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-20',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'CS401',
            'name' => 'Distributed Systems',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'S-400',
            'full_name' => 'Calendar Student',
            'email' => 'calendar.student@example.com',
            'status' => 'Active',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        return compact('university', 'department', 'course', 'section', 'student');
    }

    public function test_assessment_deadline_creates_student_notification_and_calendar_event(): void
    {
        $setup = $this->makeAcademicSetup();
        $studentUser = $this->makeRoleUser('student', ['lms.view'], [
            'email' => $setup['student']->email,
        ]);
        $teacher = $this->makeRoleUser('teacher', ['lms.create_assignment']);
        $teacherRecord = Teacher::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'staff_id' => 'T-DEADLINE-1',
            'full_name' => 'Deadline Teacher',
            'email' => $teacher->email,
            'status' => 'Active',
        ]);
        $setup['section']->update(['teacher_id' => $teacherRecord->id]);

        $this->actingAs($teacher)
            ->post(route('assessment-items.store'), [
                'course_section_id' => $setup['section']->id,
                'title' => 'Term Project',
                'type' => 'project',
                'max_score' => 100,
                'weight_percent' => 25,
                'status' => 'published',
                'due_at' => '2026-10-10T09:00',
                'allow_submissions' => 1,
            ])
            ->assertRedirect(route('assessments.index'));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentUser->id,
            'student_id' => $setup['student']->id,
            'type' => 'assignment_deadline',
            'title' => 'Assessment due: Term Project',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'student_id' => $setup['student']->id,
            'category' => 'assignment_deadline',
            'title' => 'Assessment due: Term Project',
            'source_type' => AssessmentItem::class,
        ]);
        $this->assertDatabaseHas('calendar_events', [
            'user_id' => $teacher->id,
            'student_id' => null,
            'category' => 'assignment_deadline',
            'title' => 'Assessment due: Term Project',
            'source_type' => AssessmentItem::class,
        ]);

        $this->actingAs($teacher)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Assessment due: Term Project');
    }

    public function test_finance_invoice_creates_payment_due_notification_and_event(): void
    {
        $setup = $this->makeAcademicSetup();
        $studentUser = $this->makeRoleUser('student', ['finance.view'], [
            'email' => $setup['student']->email,
        ]);
        $accountant = $this->makeRoleUser('accountant', ['finance.view', 'finance.create_invoice']);
        $accountant->update([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['student']->department_id,
        ]);
        $invoicePermission = Permission::where('name', 'finance.create_invoice')->first();
        $accountant->permissionOverrides()->attach($invoicePermission, ['effect' => 'grant']);

        $this->actingAs($accountant)
            ->post(route('finance.transactions.store'), [
                'student_id' => $setup['student']->id,
                'type' => 'invoice',
                'amount' => '500000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'INV-400',
                'academic_year' => '2026/2027',
                'transaction_date' => '2026-09-10',
                'due_date' => '2026-09-30',
            ])
            ->assertRedirect(route('finance.students.show', $setup['student']));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentUser->id,
            'student_id' => $setup['student']->id,
            'type' => 'payment_due',
            'title' => 'Payment due: INV-400',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'student_id' => $setup['student']->id,
            'category' => 'payment_due',
            'source_type' => FinanceTransaction::class,
        ]);
    }

    public function test_attendance_absent_creates_attendance_alert(): void
    {
        $setup = $this->makeAcademicSetup();
        $studentUser = $this->makeRoleUser('student', ['attendance.view'], [
            'email' => $setup['student']->email,
        ]);
        $teacher = $this->makeRoleUser('teacher', ['attendance.view', 'attendance.create', 'attendance.update']);
        $teacherRecord = Teacher::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'staff_id' => 'T-CALENDAR-1',
            'full_name' => 'Calendar Teacher',
            'email' => $teacher->email,
            'status' => 'Active',
        ]);
        $setup['section']->update(['teacher_id' => $teacherRecord->id]);

        $this->actingAs($teacher)
            ->postJson(route('attendance.store', ['course' => $setup['course'], 'section_id' => $setup['section']->id]), [
                'attendance' => [
                    $setup['student']->id => 'absent',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentUser->id,
            'student_id' => $setup['student']->id,
            'type' => 'attendance_alert',
            'severity' => 'danger',
        ]);
    }

    public function test_mark_publication_creates_marks_published_notification_and_can_be_read(): void
    {
        $setup = $this->makeAcademicSetup();
        $studentUser = $this->makeRoleUser('student', ['marks.view'], [
            'email' => $setup['student']->email,
        ]);
        $admin = $this->makeRoleUser('examination_administrator', ['marks.publish'], [
            'university_id' => $setup['university']->id,
        ]);
        $mark = Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 91,
            'status' => 'Draft',
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->postJson(route('marks.publish'), [
                'mark_ids' => [$mark->id],
            ])
            ->assertOk();

        $notification = AppNotification::where('user_id', $studentUser->id)
            ->where('type', 'marks_published')
            ->firstOrFail();

        $this->actingAs($studentUser)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Marks published');

        $this->actingAs($studentUser)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
