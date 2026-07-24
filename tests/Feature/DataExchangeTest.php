<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_students_from_csv(): void
    {
        $admin = $this->makeAdmin();
        [$university, $department] = $this->makeStructure();
        $file = UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_id,full_name,email,phone,department,status,admission_status\n".
            "CSV-1001,CSV Student,csv-student@example.com,0770000000,{$department->code},Active,Admitted\n"
        );

        $this->actingAs($admin)
            ->post(route('integrations.import.students'), ['csv_file' => $file])
            ->assertRedirect(route('integrations.index'));

        $this->assertDatabaseHas('students', [
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'CSV-1001',
            'email' => 'csv-student@example.com',
        ]);
        $this->assertDatabaseHas('import_logs', [
            'type' => 'students',
            'total_rows' => 1,
            'successful' => 1,
            'failed' => 0,
        ]);
    }

    public function test_admin_can_import_courses_and_export_students(): void
    {
        $admin = $this->makeAdmin();
        [, $department] = $this->makeStructure();
        $file = UploadedFile::fake()->createWithContent(
            'courses.csv',
            "code,name,department,credits,semester\n".
            "CSV101,CSV Course,{$department->code},3,Fall\n"
        );

        $this->actingAs($admin)
            ->post(route('integrations.import.courses'), ['csv_file' => $file])
            ->assertRedirect(route('integrations.index'));

        $this->assertDatabaseHas('courses', [
            'code' => 'CSV101',
            'name' => 'CSV Course',
            'department_id' => $department->id,
        ]);

        Student::create([
            'university_id' => $department->university_id,
            'department_id' => $department->id,
            'student_id' => 'EXP-1001',
            'full_name' => 'Export Student',
            'email' => 'export@example.com',
            'status' => 'Active',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('integrations.export.students'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Student ID', $csv);
        $this->assertStringContainsString('EXP-1001', $csv);
        $this->assertStringContainsString('Export Student', $csv);
    }

    public function test_data_exchange_workspace_shows_tabs_filters_and_new_exports(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStructure();

        $this->actingAs($admin)
            ->get(route('integrations.index', ['tab' => 'exports']))
            ->assertOk()
            ->assertSee('Data Import / Export')
            ->assertSee('Imports')
            ->assertSee('Exports')
            ->assertSee('API')
            ->assertSee('Import History')
            ->assertSee('College')
            ->assertSee('Department')
            ->assertSee('Stage')
            ->assertSee('Enrollments / Modules')
            ->assertSee('Timetable')
            ->assertSee('Finance');
    }

    public function test_department_admin_exports_and_api_are_scoped_to_own_department(): void
    {
        [$university, $department] = $this->makeStructure();
        $otherDepartment = Department::create([
            'university_id' => $university->id,
            'college_id' => $department->college_id,
            'name' => 'Electrical Engineering',
            'code' => 'EE',
        ]);
        $admin = $this->makeDepartmentAdmin($university, $department);
        $ownStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'OWN-1001',
            'full_name' => 'Own Student',
            'email' => 'own@example.com',
            'status' => 'Active',
        ]);
        Student::create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'OTHER-1001',
            'full_name' => 'Other Student',
            'email' => 'other@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'CS301',
            'name' => 'Scoped Course',
            'credits' => 3,
        ]);
        Course::create([
            'department_id' => $otherDepartment->id,
            'code' => 'EE301',
            'name' => 'Other Course',
            'credits' => 3,
        ]);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 2',
        ]);
        Enrollment::create([
            'student_id' => $ownStudent->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $studentsCsv = $this->actingAs($admin)
            ->get(route('integrations.export.students'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('OWN-1001', $studentsCsv);
        $this->assertStringNotContainsString('OTHER-1001', $studentsCsv);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('data.0.student_id', 'OWN-1001')
            ->assertJsonMissing(['student_id' => 'OTHER-1001']);

        $filteredEnrollments = $this->actingAs($admin)
            ->get(route('integrations.export.enrollments', ['stage' => 'Stage 2', 'semester_id' => $semester->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('OWN-1001', $filteredEnrollments);
        $this->assertStringContainsString('Stage 2', $filteredEnrollments);
    }

    public function test_authenticated_api_returns_students_for_authorized_user(): void
    {
        $admin = $this->makeAdmin();
        [$university, $department] = $this->makeStructure();
        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'API-1001',
            'full_name' => 'API Student',
            'email' => 'api-student@example.com',
            'status' => 'Active',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('data.0.student_id', 'API-1001')
            ->assertJsonPath('data.0.department.name', $department->name);
    }

    public function test_api_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_data_exchange_even_with_data_permissions(): void
    {
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        foreach (['students.view', 'courses.view', 'marks.view', 'marks.enter'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $teacher = User::factory()->create();
        $teacher->roles()->attach($role);

        $this->actingAs($teacher)->get(route('integrations.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('integrations.samples.marks'))->assertForbidden();
        $this->actingAs($teacher)->get(route('integrations.export.marks'))->assertForbidden();
    }

    private function makeAdmin(): User
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

    private function makeStructure(): array
    {
        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $college = College::create([
            'university_id' => $university->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        return [$university, $department];
    }

    private function makeDepartmentAdmin(University $university, Department $department): User
    {
        $role = Role::create([
            'name' => 'department_administrator',
            'display_name' => 'Department Administrator',
            'description' => 'Department-scoped user',
        ]);
        foreach (['students.view', 'courses.view', 'enrollments.view', 'marks.view'] as $permissionName) {
            $permission = Permission::create(['name' => $permissionName, 'display_name' => $permissionName]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }
}
