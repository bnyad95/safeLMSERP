<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TuitionRate;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuitionRateManagementTest extends TestCase
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

    private function makeScopedAdmin(University $university, Department $department): User
    {
        $role = Role::firstOrCreate(['name' => 'department_administrator'], ['display_name' => 'Department Administrator']);
        $permission = Permission::firstOrCreate(['name' => 'academic_setup.manage'], ['display_name' => 'Manage Academic Setup']);

        $user = User::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $user->roles()->attach($role);
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);

        return $user;
    }

    private function organization(string $suffix): array
    {
        $university = University::create(['name' => "University {$suffix}", 'code' => "U{$suffix}"]);
        $department = Department::create(['university_id' => $university->id, 'name' => "Department {$suffix}"]);

        return [$university, $department];
    }

    public function test_super_administrator_can_save_department_year_currency_rates(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('SAVE');
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('bologna-definition.tuition-rates'))
            ->assertOk()
            ->assertSee($department->name);

        $this->actingAs($admin)
            ->post(route('bologna-definition.tuition-rates.store'), [
                'rates' => [
                    $department->id => [
                        $academicYear->id => ['IQD' => '75000', 'USD' => '55.50'],
                    ],
                ],
            ])
            ->assertRedirect(route('bologna-definition.tuition-rates'));

        $this->assertDatabaseHas('tuition_rates', [
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => '75000.00',
        ]);
        $this->assertDatabaseHas('tuition_rates', [
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'USD',
            'rate_per_credit' => '55.50',
        ]);
    }

    public function test_rate_out_of_bounds_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('BOUNDS');
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);

        $this->actingAs($admin)
            ->from(route('bologna-definition.tuition-rates'))
            ->post(route('bologna-definition.tuition-rates.store'), [
                'rates' => [
                    $department->id => [
                        $academicYear->id => ['IQD' => '0'],
                    ],
                ],
            ])
            ->assertRedirect(route('bologna-definition.tuition-rates'))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('tuition_rates', 0);
    }

    public function test_rates_for_locked_academic_year_are_read_only(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('LOCKED');
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2025/2026', 'status' => 'closed']);

        $this->actingAs($admin)
            ->from(route('bologna-definition.tuition-rates'))
            ->post(route('bologna-definition.tuition-rates.store'), [
                'rates' => [
                    $department->id => [
                        $academicYear->id => ['IQD' => '75000'],
                    ],
                ],
            ])
            ->assertRedirect(route('bologna-definition.tuition-rates'))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('tuition_rates', 0);
    }

    public function test_department_scoped_admin_cannot_set_rate_for_another_department(): void
    {
        [$university, $department] = $this->organization('OWN');
        $otherDepartment = Department::create(['university_id' => $university->id, 'name' => 'Other Department']);
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);
        $scopedAdmin = $this->makeScopedAdmin($university, $department);

        $this->actingAs($scopedAdmin)
            ->post(route('bologna-definition.tuition-rates.store'), [
                'rates' => [
                    $otherDepartment->id => [
                        $academicYear->id => ['IQD' => '75000'],
                    ],
                ],
            ]);

        $this->assertDatabaseCount('tuition_rates', 0);
    }
}
