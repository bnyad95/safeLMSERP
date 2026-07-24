<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::factory()->create();
        $this->course = Course::factory()->create([
            'department_id' => $this->department->id,
        ]);
        $this->student = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
        ]);
        $this->teacher = User::factory()->create(['email' => 'teacher@test.com']);
        $teacherRecord = Teacher::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'staff_id' => 'T-ATT-1',
            'full_name' => 'Attendance Teacher',
            'email' => $this->teacher->email,
            'status' => 'Active',
        ]);
        $semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacherRecord->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->createRoles();
        $this->teacher->roles()->attach($this->getRole('teacher'));
    }

    private function createRoles()
    {
        if (! Role::where('name', 'teacher')->exists()) {
            $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
            $permissions = collect(['attendance.view', 'attendance.create', 'attendance.update'])
                ->map(fn ($name) => Permission::create(['name' => $name, 'display_name' => $name]));
            $role->permissions()->attach($permissions->pluck('id'));
        }
    }

    private function getRole($name)
    {
        return Role::where('name', $name)->first();
    }

    public function test_teacher_can_record_attendance()
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson("/courses/{$this->course->id}/attendance", [
            'attendance' => [
                $this->student->id => 'present',
            ],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('attendances', [
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'status' => 'present',
        ]);
    }

    public function test_teacher_only_sees_and_records_students_in_the_selected_class()
    {
        $outsideStudent = Student::factory()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->teacher)
            ->get(route('attendance.index', ['course' => $this->course, 'section_id' => $this->section->id]))
            ->assertOk()
            ->assertSee($this->student->full_name)
            ->assertDontSee($outsideStudent->full_name);

        $this->actingAs($this->teacher)
            ->postJson(route('attendance.store', ['course' => $this->course, 'section_id' => $this->section->id]), [
                'attendance' => [$outsideStudent->id => 'present'],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $outsideStudent->id,
            'course_section_id' => $this->section->id,
        ]);
    }

    public function test_university_administrator_can_open_and_update_scoped_section_attendance(): void
    {
        $role = Role::create(['name' => 'university_administrator', 'display_name' => 'University Administrator']);
        $permissions = collect(['attendance.view', 'attendance.update'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['display_name' => $name]));
        $role->permissions()->attach($permissions->pluck('id'));
        $admin = User::factory()->create(['university_id' => $this->department->university_id]);
        $admin->roles()->attach($role);

        $this->actingAs($admin)
            ->get(route('attendance'))
            ->assertOk()
            ->assertSee('Group A')
            ->assertSee(route('attendance.index', ['course' => $this->course->id, 'section_id' => $this->section->id]), false);

        $this->actingAs($admin)
            ->get(route('attendance.index', ['course' => $this->course->id, 'section_id' => $this->section->id]))
            ->assertOk()
            ->assertSee('Save Attendance');

        $this->actingAs($admin)
            ->postJson(route('attendance.store', ['course' => $this->course->id, 'section_id' => $this->section->id]), [
                'attendance' => [$this->student->id => 'late'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendances', [
            'course_section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'status' => 'late',
        ]);
    }

    public function test_attendance_service_calculates_report()
    {
        $service = new AttendanceService;

        // Create attendance records
        Attendance::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'status' => 'present',
            'date' => now(),
        ]);
        Attendance::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'status' => 'absent',
            'date' => now()->subDay(),
        ]);

        $report = $service->getAttendanceReport($this->course->id, $this->student->id);

        $this->assertEquals(2, $report['total_classes']);
        $this->assertEquals(1, $report['present']);
        $this->assertEquals(1, $report['absent']);
        $this->assertEquals(50, $report['attendance_percentage']);
    }

    public function test_attendance_service_records_multiple_students()
    {
        $students = Student::factory(3)->create([
            'department_id' => $this->department->id,
        ]);

        $service = new AttendanceService;
        $result = $service->recordAttendance($this->course->id, [
            $students[0]->id => 'present',
            $students[1]->id => 'absent',
            $students[2]->id => 'late',
        ]);

        $this->assertEquals(3, $result['created']);
        $this->assertDatabaseCount('attendances', 3);
    }

    public function test_attendance_bulk_import()
    {
        $students = Student::factory(2)->create([
            'department_id' => $this->department->id,
        ]);

        $service = new AttendanceService;
        $result = $service->bulkImportAttendance(
            $this->course->id,
            now(),
            [
                $students[0]->id => 'present',
                $students[1]->id => 'absent',
            ]
        );

        $this->assertEquals(2, $result['created']);
    }

    public function test_attendance_update_creates_no_duplicates()
    {
        $service = new AttendanceService;

        // First create attendance via service to get proper date format
        $result1 = $service->recordAttendance($this->course->id, [
            $this->student->id => 'present',
        ]);

        // Now update via service — should update, not create
        $result2 = $service->recordAttendance($this->course->id, [
            $this->student->id => 'absent',
        ]);

        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(1, $result2['updated']);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student->id,
            'status' => 'absent',
        ]);
    }
}
