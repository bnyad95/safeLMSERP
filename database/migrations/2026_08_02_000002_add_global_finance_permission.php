<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'finance.view_global',
            'display_name' => 'View finance data across all organizations',
            'description' => 'View finance data across all organizations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'finance.view_global')->value('id');
        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('permission_user')) {
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        }
        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
