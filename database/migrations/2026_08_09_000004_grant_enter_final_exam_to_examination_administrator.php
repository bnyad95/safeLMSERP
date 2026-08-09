<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'marks.enter_final_exam')->value('id');
        $roleId = DB::table('roles')->where('name', 'examination_administrator')->value('id');

        if ($permissionId && $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'marks.enter_final_exam')->value('id');
        $roleId = DB::table('roles')->where('name', 'examination_administrator')->value('id');

        if ($permissionId && $roleId) {
            DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->delete();
        }
    }
};
