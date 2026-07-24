<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('department_id')->constrained()->nullOnDelete();
        });

        DB::table('teachers')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->each(function ($teacher) {
                $userId = DB::table('users')->where('email', $teacher->email)->value('id');

                if ($userId) {
                    DB::table('teachers')->where('id', $teacher->id)->update(['user_id' => $userId]);
                }
            });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'teachers.archive'],
            [
                'display_name' => 'Archive teachers',
                'description' => 'Archive and restore teacher records',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')->where('name', 'teachers.archive')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['super_administrator', 'administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'hr_manager'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'teachers.archive')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
