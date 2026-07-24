<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_student_creates_or_links_student_user_account(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

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

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Student Test',
                'student_id' => 'STD-4001',
                'email' => 'student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
            ])
            ->assertRedirect('/students');

        $studentUser = User::where('email', 'student@example.com')->first();

        $this->assertNotNull($studentUser);
        $this->assertTrue(Hash::check('Temporary123', $studentUser->password));
        $this->assertTrue($studentUser->must_change_password);
        $this->assertTrue($studentUser->roles()->where('name', 'student')->exists());
    }
}
