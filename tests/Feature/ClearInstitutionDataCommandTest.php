<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearInstitutionDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_is_required_and_authorization_data_is_preserved(): void
    {
        $superRole = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
        ]);
        $otherRole = Role::create([
            'name' => 'registrar',
            'display_name' => 'Registrar',
        ]);
        $permission = Permission::create([
            'name' => 'students.view',
            'display_name' => 'View students',
            'module' => 'students',
        ]);
        $superRole->permissions()->attach($permission);

        $university = University::create([
            'name' => 'University to remove',
            'code' => 'REMOVE',
        ]);
        $administrator = User::factory()->create([
            'university_id' => $university->id,
        ]);
        $administrator->roles()->attach($superRole);
        $ordinaryUser = User::factory()->create();
        $ordinaryUser->roles()->attach($otherRole);

        $this->artisan('safelms:clear-institution-data', ['--confirm' => 'wrong'])
            ->expectsOutput('Confirmation failed. No data was deleted.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('universities', ['id' => $university->id]);

        $this->artisan('safelms:clear-institution-data', [
            '--confirm' => 'DELETE-UNIVERSITY-DATA',
        ])->assertSuccessful();

        $this->assertDatabaseCount('universities', 0);
        $this->assertDatabaseMissing('users', ['id' => $ordinaryUser->id]);
        $this->assertDatabaseHas('users', [
            'id' => $administrator->id,
            'university_id' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('roles', ['id' => $superRole->id]);
        $this->assertDatabaseHas('roles', ['id' => $otherRole->id]);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $superRole->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertDatabaseHas('role_user', [
            'role_id' => $superRole->id,
            'user_id' => $administrator->id,
        ]);
    }
}
