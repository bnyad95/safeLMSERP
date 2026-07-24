<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['auditor', 'guest_user'])
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
        }

        if (Schema::hasTable('role_user')) {
            DB::table('role_user')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }

    public function down(): void
    {
        // Retired roles are intentionally not restored on rollback.
    }
};
