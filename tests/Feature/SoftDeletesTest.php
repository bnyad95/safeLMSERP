<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_be_soft_deleted()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $userId = $user->id;
        $user->delete();

        // Verify deleted record is not in normal queries
        $this->assertNull(User::find($userId));

        // Verify deleted record exists in database with soft delete
        $this->assertNotNull(User::withTrashed()->find($userId));
    }

    #[Test]
    public function only_deleted_records_can_be_restored()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $userId = $user->id;
        $user->delete();

        // Restore the deleted user
        $restored = User::withTrashed()->find($userId);
        $restored->restore();

        // Verify user is now visible in normal queries
        $this->assertNotNull(User::find($userId));
    }

    #[Test]
    public function force_delete_permanently_removes_record()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $userId = $user->id;
        $user->forceDelete();

        // Verify permanently deleted
        $this->assertNull(User::withTrashed()->find($userId));
    }

    #[Test]
    public function soft_deleted_record_has_deleted_at_timestamp()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->delete();
        $deletedUser = User::withTrashed()->find($user->id);

        $this->assertNotNull($deletedUser->deleted_at);
        $this->assertTrue($deletedUser->trashed());
    }

    #[Test]
    public function semester_can_be_soft_deleted_and_retrieved_with_trashed()
    {
        $university = University::create([
            'name' => 'Test University',
            'code' => 'TEST',
            'email' => 'test@example.com',
            'phone' => '1234567890',
        ]);

        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2024/2025',
            'start_date' => '2024-08-01',
            'end_date' => '2024-12-31',
        ]);

        $semesterId = $semester->id;
        $semester->delete();

        $this->assertNull(Semester::find($semesterId));
        $this->assertNotNull(Semester::withTrashed()->find($semesterId));
    }

    #[Test]
    public function only_trashed_retrieves_only_deleted_records()
    {
        $user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
        ]);

        $user1->delete();

        $trashedUsers = User::onlyTrashed()->get();
        $this->assertCount(1, $trashedUsers);
        $this->assertEquals($user1->id, $trashedUsers->first()->id);
    }
}
