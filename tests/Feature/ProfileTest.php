<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_student_account_settings_show_academic_profile(): void
    {
        $role = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $user = User::factory()->create([
            'name' => 'Student Account',
            'email' => 'account@example.com',
        ]);
        $user->roles()->attach($role);
        $university = University::create(['name' => 'Profile University', 'code' => 'PU']);
        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Profile Department',
            'code' => 'PD',
        ]);
        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'user_id' => $user->id,
            'student_id' => 'PROFILE-1',
            'full_name' => 'Profile Student',
            'email' => 'student-record@example.com',
            'status' => 'Active',
            'admission_status' => 'Admitted',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Account Settings')
            ->assertSee('Academic Profile')
            ->assertSee('PROFILE-1')
            ->assertSee('Profile University')
            ->assertSee('Profile Department');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // User should be soft deleted, not completely removed
        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
