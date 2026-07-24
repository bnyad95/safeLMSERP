<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_role_dashboards_render_or_redirect_to_their_workspace(): void
    {
        $this->actingAs($this->roleUser('super_administrator'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ERP Overview');

        $this->actingAs($this->roleUser('teacher'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teacher Dashboard');

        $this->actingAs($this->studentUser())
            ->get(route('student-portal'))
            ->assertOk()
            ->assertSee('Student Portal');

        $this->actingAs($this->roleUser('examination_committee', ['marks.view', 'marks.review', 'marks.approve']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Examination Committee Dashboard');

        $this->actingAs($this->roleUser('accountant', ['finance.view']))
            ->get(route('finance'))
            ->assertOk()
            ->assertSee('Finance');

        $this->actingAs($this->roleUser('librarian'))
            ->get(route('dashboard'))
            ->assertRedirect(route('library.workspace'));

        $this->actingAs($this->roleUser('receptionist'))
            ->get(route('dashboard'))
            ->assertRedirect(route('reception.workspace'));
    }

    private function roleUser(string $roleName, array $permissions = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => str($roleName)->headline()->toString()]
        );

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => str($permissionName)->headline()->toString()]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function studentUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student User']
        );
        $department = Department::factory()->create();
        $student = Student::factory()->create([
            'university_id' => $department->university_id,
            'department_id' => $department->id,
            'email' => 'smoke.student@example.com',
        ]);
        $user = User::factory()->create([
            'email' => $student->email,
            'university_id' => $student->university_id,
            'department_id' => $student->department_id,
        ]);
        $user->roles()->attach($role);
        $student->update(['user_id' => $user->id]);

        return $user;
    }
}
