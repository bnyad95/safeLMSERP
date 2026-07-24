<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
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

    public function test_user_without_students_permission_cannot_see_students_section(): void
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
