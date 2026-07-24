<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_user_can_search_catalog_and_open_course_profile(): void
    {
        [, $college, $department] = $this->organization('VIEW');
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'VIEW101',
            'name' => 'Catalog Architecture',
            'credits' => 4,
            'status' => 'active',
        ]);
        $user = $this->userWithPermissions('catalog_reader', ['courses.view']);

        $this->actingAs($user)
            ->get(route('course-records.index', ['q' => 'Architecture', 'college_id' => $college->id, 'status' => 'active']))
            ->assertOk()
            ->assertSee('Catalog Architecture')
            ->assertSee('Academic classification')
            ->assertDontSee('Add Course')
            ->assertDontSee('Archived (');

        $this->actingAs($user)
            ->get(route('course-records.show', $course))
            ->assertOk()
            ->assertSee('Read-only course catalog profile')
            ->assertDontSee('Edit Course');

        $this->actingAs($user)->get(route('course-records.create'))->assertForbidden();
        $this->actingAs($user)->get(route('course-records.edit', $course))->assertForbidden();
        $this->actingAs($user)->delete(route('course-records.destroy', $course))->assertForbidden();
    }

    public function test_create_permission_does_not_grant_view_update_or_archive(): void
    {
        [, $college, $department] = $this->organization('CREATE');
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'OLD101',
            'name' => 'Existing Course',
            'credits' => 3,
            'status' => 'active',
        ]);
        $user = $this->userWithPermissions('catalog_creator', ['courses.create']);

        $this->actingAs($user)->get(route('course-records.index'))->assertForbidden();
        $this->actingAs($user)->post(route('course-records.store'), [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'code' => 'NEW101',
            'name' => 'New Course',
            'credits' => 3,
            'semester' => 'Fall',
        ])->assertRedirect(route('course-records.index'));

        $this->assertDatabaseHas('courses', ['code' => 'NEW101', 'status' => 'active', 'semester' => null]);
        $this->actingAs($user)->put(route('course-records.update', $course), [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'code' => 'OLD101',
            'name' => 'Changed Course',
            'credits' => 3,
            'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('course-records.destroy', $course))->assertForbidden();
    }

    public function test_course_codes_are_unique_per_university(): void
    {
        [, $firstCollege, $firstDepartment] = $this->organization('ONE');
        [, $secondCollege, $secondDepartment] = $this->organization('TWO');
        $admin = $this->superAdmin();

        $payload = ['code' => 'CS101', 'name' => 'Shared Code', 'credits' => 3, 'status' => 'active'];
        $this->actingAs($admin)->post(route('course-records.store'), $payload + ['college_id' => $firstCollege->id, 'department_id' => $firstDepartment->id])->assertRedirect();
        $this->actingAs($admin)->post(route('course-records.store'), $payload + ['college_id' => $secondCollege->id, 'department_id' => $secondDepartment->id])->assertRedirect();
        $this->assertSame(2, Course::where('code', 'CS101')->count());

        $this->actingAs($admin)
            ->post(route('course-records.store'), $payload + ['college_id' => $firstCollege->id, 'department_id' => $firstDepartment->id])
            ->assertSessionHasErrors('code');
    }

    public function test_course_department_must_belong_to_selected_college(): void
    {
        [, $firstCollege] = $this->organization('COLLEGE-A');
        [, , $secondDepartment] = $this->organization('COLLEGE-B');
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('course-records.store'), [
                'college_id' => $firstCollege->id,
                'department_id' => $secondDepartment->id,
                'code' => 'BAD101',
                'name' => 'Mismatched Course',
                'credits' => 3,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('department_id');
    }

    public function test_course_with_open_sections_must_be_closed_before_archive_and_can_be_restored(): void
    {
        [$university, , $department] = $this->organization('ARCH');
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'ARCH101', 'name' => 'Archive Course', 'credits' => 3, 'status' => 'inactive']);
        $section = CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'section_code' => 'A', 'status' => 'active']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->delete(route('course-records.destroy', $course))
            ->assertSessionHas('error', 'Close all planned and active sections before archiving this course.');
        $this->assertNotSoftDeleted('courses', ['id' => $course->id]);

        $section->update(['status' => 'closed']);
        $this->actingAs($admin)->delete(route('course-records.destroy', $course))->assertRedirect(route('course-records.index'));
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertSame('ARCH101', $section->fresh()->course->code);

        $this->actingAs($admin)->patch(route('course-records.restore', $course->id))->assertRedirect(route('course-records.archived'));
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }

    public function test_teacher_assignment_requires_enrollment_management_and_assignment_permissions(): void
    {
        [$university, , $department] = $this->organization('ASSIGN');
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'ASSIGN101', 'name' => 'Assignment Course', 'credits' => 3, 'status' => 'active']);
        $teacher = Teacher::create(['university_id' => $university->id, 'department_id' => $department->id, 'staff_id' => 'T-ASSIGN', 'full_name' => 'Assigned Teacher', 'email' => 'assigned@example.com', 'status' => 'Active']);
        $manager = $this->userWithPermissions('enrollment_manager', ['enrollments.manage']);
        $payload = ['course_id' => $course->id, 'semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'section_code' => 'A', 'capacity' => 30, 'status' => 'active'];

        $this->actingAs($manager)->post(route('course-sections.store'), $payload)->assertForbidden();

        $assigner = $this->userWithPermissions('section_assigner', ['enrollments.manage', 'courses.assign_teacher']);
        $this->actingAs($assigner)->post(route('course-sections.store'), $payload)->assertRedirect(route('enrollments.index'));
        $this->assertDatabaseHas('course_sections', ['course_id' => $course->id, 'teacher_id' => $teacher->id]);
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_administrator'], ['display_name' => 'Super Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function userWithPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName], ['display_name' => str($permissionName)->replace('.', ' ')->title()]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function organization(string $suffix): array
    {
        $university = University::create(['name' => "University {$suffix}", 'code' => "U{$suffix}"]);
        $college = College::create(['university_id' => $university->id, 'name' => "College {$suffix}", 'code' => "C{$suffix}"]);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => "Department {$suffix}", 'code' => "D{$suffix}"]);

        return [$university, $college, $department];
    }
}
