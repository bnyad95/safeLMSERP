<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_and_see_student_results(): void
    {
        $role = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

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

        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-1001',
            'full_name' => 'Ahmed Searchable',
            'email' => 'ahmed@example.com',
            'phone' => '0770000000',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get('/search?q=Ahmed')
            ->assertOk()
            ->assertSee('Global Search')
            ->assertSee('Ahmed Searchable');
    }

    public function test_user_without_students_permission_and_no_role_access_cannot_see_students_section(): void
    {
        $role = Role::create([
            'name' => 'librarian',
            'display_name' => 'Library Administrator',
            'description' => 'Read-only academic resource access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Law',
            'code' => 'LAW',
        ]);

        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-2001',
            'full_name' => 'Hidden Student',
            'email' => 'hidden@example.com',
            'phone' => '0771111111',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get('/search?q=Hidden')
            ->assertOk()
            ->assertDontSee('Students')
            ->assertDontSee('Hidden Student');
    }

    public function test_teacher_search_is_scoped_to_their_own_students(): void
    {
        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Instructor',
            'description' => 'Teacher role',
        ]);

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

        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-20',
        ]);

        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'CS201',
            'name' => 'Data Structures',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);

        $teacherRecord = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-100',
            'full_name' => 'Dr. Rana Aziz',
            'email' => 'rana@example.com',
            'status' => 'Active',
        ]);

        $user = User::factory()->create(['email' => 'rana@example.com']);
        $user->roles()->attach($teacherRole->id);

        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacherRecord->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $ownStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-3001',
            'full_name' => 'Own Enrolled Student',
            'email' => 'own@example.com',
            'status' => 'Active',
        ]);

        Enrollment::create([
            'student_id' => $ownStudent->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-3002',
            'full_name' => 'Unrelated Student',
            'email' => 'unrelated@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get('/search?q=Student')
            ->assertOk()
            ->assertSee('Own Enrolled Student')
            ->assertDontSee('Unrelated Student');
    }

    public function test_super_admin_gets_live_suggestions(): void
    {
        $role = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-3001',
            'full_name' => 'Ali Suggestion',
            'email' => 'ali@example.com',
            'phone' => '0772222222',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->getJson('/search/suggestions?q=Ali')
            ->assertOk()
            ->assertJsonPath('items.0.type', 'Student')
            ->assertJsonFragment(['title' => 'Ali Suggestion']);
    }

    public function test_user_without_student_permission_does_not_receive_student_suggestions(): void
    {
        $teacherRole = Role::create([
            'name' => 'teacher',
            'display_name' => 'Instructor',
            'description' => 'Teacher role',
        ]);

        $coursesView = Permission::create([
            'name' => 'courses.view',
            'display_name' => 'View courses',
            'description' => 'View courses',
        ]);

        $teacherRole->permissions()->attach($coursesView->id);

        $user = User::factory()->create();
        $user->roles()->attach($teacherRole->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Medicine',
            'code' => 'MED',
        ]);

        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-3002',
            'full_name' => 'Hidden Suggestion',
            'email' => 'hidden.suggestion@example.com',
            'phone' => '0773333333',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->getJson('/search/suggestions?q=Hidden')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Hidden Suggestion']);
    }
}
