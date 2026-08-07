<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_librarian_can_open_library_workspace_and_filter_resources(): void
    {
        $role = Role::create(['name' => 'librarian', 'display_name' => 'Library Administrator']);
        $department = Department::factory()->create();
        $user = User::factory()->create([
            'university_id' => $department->university_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $user->roles()->attach($role);
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'code' => 'LIB101',
            'name' => 'Library Systems',
        ]);
        CourseMaterial::create([
            'course_id' => $course->id,
            'title' => 'Catalog Guide',
            'description' => 'Library resource guide',
            'file_type' => 'pdf',
            'visibility' => 'published',
            'uploaded_by' => $user->id,
        ]);
        CourseMaterial::create([
            'course_id' => $course->id,
            'title' => 'Hidden Draft',
            'file_type' => 'video',
            'visibility' => 'draft',
            'uploaded_by' => $user->id,
        ]);
        $outsideDepartment = Department::factory()->create();
        $outsideCourse = Course::factory()->create([
            'department_id' => $outsideDepartment->id,
            'code' => 'OUT101',
            'name' => 'Outside Library Course',
        ]);
        CourseMaterial::create([
            'course_id' => $outsideCourse->id,
            'title' => 'Outside Organization Guide',
            'file_type' => 'pdf',
            'visibility' => 'published',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('library.workspace'));

        $this->actingAs($user)
            ->get(route('library.workspace', ['q' => 'Catalog', 'file_type' => 'pdf', 'visibility' => 'published']))
            ->assertOk()
            ->assertSee('Library Workspace')
            ->assertSee('Catalog Guide')
            ->assertDontSee('Hidden Draft')
            ->assertDontSee('Outside Organization Guide');

        $this->actingAs($user)
            ->get(route('library.workspace'))
            ->assertOk()
            ->assertSee('Catalog Guide')
            ->assertDontSee('Outside Organization Guide');
    }

    public function test_parent_user_sees_only_students_linked_by_guardian_email(): void
    {
        $role = Role::create(['name' => 'parent_user', 'display_name' => 'Parent User']);
        $parent = User::factory()->create(['email' => 'parent@example.com']);
        $parent->roles()->attach($role);
        $department = Department::factory()->create();
        $parent->update([
            'university_id' => $department->university_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $course = Course::factory()->create(['department_id' => $department->id, 'name' => 'Parent Visible Course']);
        $semester = Semester::create([
            'university_id' => $department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $student = Student::factory()->create([
            'university_id' => $department->university_id,
            'department_id' => $department->id,
            'full_name' => 'Linked Parent Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $department->university_id,
            'department_id' => $department->id,
            'full_name' => 'Unlinked Parent Student',
        ]);
        StudentGuardian::create([
            'student_id' => $student->id,
            'full_name' => 'Parent Guardian',
            'relationship' => 'Parent',
            'email' => $parent->email,
            'is_primary' => true,
        ]);
        StudentGuardian::create([
            'student_id' => $hiddenStudent->id,
            'full_name' => 'Other Guardian',
            'relationship' => 'Parent',
            'email' => 'other-parent@example.com',
            'is_primary' => true,
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        Mark::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'final_mark' => 88,
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        Attendance::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'present',
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => 250,
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => today(),
        ]);

        $this->actingAs($parent)
            ->get(route('dashboard'))
            ->assertRedirect(route('parent.workspace'));

        $this->actingAs($parent)
            ->get(route('parent.workspace'))
            ->assertOk()
            ->assertSee('Parent Portal')
            ->assertSee('Linked Parent Student')
            ->assertSee('Parent Visible Course')
            ->assertSee('250.00 USD')
            ->assertDontSee('Unlinked Parent Student');
    }

    public function test_receptionist_can_open_front_desk_lookup(): void
    {
        $role = Role::create(['name' => 'receptionist', 'display_name' => 'Front Desk User']);
        $department = Department::factory()->create(['name' => 'Reception Department']);
        $otherDepartment = Department::factory()->create(['name' => 'Other Reception Department']);
        $user = User::factory()->create([
            'university_id' => $department->university_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ]);
        $user->roles()->attach($role);
        $student = Student::factory()->create([
            'university_id' => $department->university_id,
            'department_id' => $department->id,
            'full_name' => 'Reception Visible Student',
            'phone' => '555-1000',
            'status' => 'Active',
        ]);
        StudentGuardian::create([
            'student_id' => $student->id,
            'full_name' => 'Reception Guardian',
            'relationship' => 'Parent',
            'phone' => '555-2000',
            'is_primary' => true,
        ]);
        Student::factory()->create([
            'university_id' => $otherDepartment->university_id,
            'department_id' => $otherDepartment->id,
            'full_name' => 'Reception Hidden Student',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('reception.workspace'));

        $this->actingAs($user)
            ->get(route('reception.workspace', ['q' => 'Visible', 'status' => 'Active']))
            ->assertOk()
            ->assertSee('Reception Workspace')
            ->assertSee('Reception Visible Student')
            ->assertSee('Reception Guardian')
            ->assertDontSee('Reception Hidden Student');
    }
}
