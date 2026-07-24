<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected $rolePermissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rolePermissionService = new RolePermissionService;
    }

    #[Test]
    public function assigning_role_to_user_is_logged()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);

        $this->rolePermissionService->assignRoleToUser($user, $role->id);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'role_assignment',
            'description' => 'role_attached',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    #[Test]
    public function removing_role_from_user_is_logged()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);

        $user->roles()->attach($role->id);
        $this->rolePermissionService->removeRoleFromUser($user, $role->id);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'role_assignment',
            'description' => 'role_detached',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    #[Test]
    public function assigning_permission_to_role_is_logged()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $permission = Permission::create(['name' => 'edit_posts', 'display_name' => 'Edit Posts']);

        $this->rolePermissionService->assignPermissionToRole($role->id, $permission->id);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'permission_assignment',
            'description' => 'permission_attached_to_role',
            'causer_id' => $admin->id,
        ]);
    }

    #[Test]
    public function removing_permission_from_role_is_logged()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $permission = Permission::create(['name' => 'edit_posts', 'display_name' => 'Edit Posts']);

        $role->permissions()->attach($permission->id);
        $this->rolePermissionService->removePermissionFromRole($role->id, $permission->id);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'permission_assignment',
            'description' => 'permission_detached_from_role',
            'causer_id' => $admin->id,
        ]);
    }

    #[Test]
    public function syncing_user_roles_logs_all_changes()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $role2 = Role::create(['name' => 'author', 'display_name' => 'Author']);
        $role3 = Role::create(['name' => 'contributor', 'display_name' => 'Contributor']);

        // Initially assign role1
        $user->roles()->attach($role1->id);

        // Sync to remove role1 and add role2 and role3
        $this->rolePermissionService->syncUserRoles($user, [$role2->id, $role3->id]);

        // Check that both attach and detach were logged
        $logs = \DB::table('activity_logs')
            ->where('log_name', 'role_assignment')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->get();

        $this->assertGreaterThanOrEqual(3, $logs->count());
    }
}
