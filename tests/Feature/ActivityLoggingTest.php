<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_creation_is_logged()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'description' => 'created',
        ]);
    }

    #[Test]
    public function user_update_is_logged()
    {
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->update(['name' => 'Updated Name']);

        $logs = \DB::table('activity_logs')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'updated')
            ->count();

        $this->assertGreaterThan(0, $logs);
    }

    #[Test]
    public function user_deletion_is_logged()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $userId = $user->id;
        $user->delete();

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => User::class,
            'subject_id' => $userId,
            'description' => 'deleted',
        ]);
    }

    #[Test]
    public function activity_log_includes_causer_information()
    {
        $this->actingAs($admin = User::factory()->create());

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'causer_type' => User::class,
            'causer_id' => $admin->id,
        ]);
    }
}
