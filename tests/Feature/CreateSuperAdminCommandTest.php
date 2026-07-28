<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_super_administrator_without_demo_data(): void
    {
        $this->artisan('safelms:create-super-admin', [
            'email' => 'owner@example.com',
            '--name' => 'System Owner',
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'StrongPassword123!')
            ->expectsQuestion('Confirm password', 'StrongPassword123!')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('System Owner', $user->name);
        $this->assertTrue($user->hasRole('super_administrator'));
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
