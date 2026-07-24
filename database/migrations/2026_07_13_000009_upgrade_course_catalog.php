<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('university_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active')->after('credits');
        });

        DB::table('courses')
            ->select(['id', 'department_id'])
            ->orderBy('id')
            ->each(function ($course) {
                $universityId = DB::table('departments')->where('id', $course->department_id)->value('university_id');

                DB::table('courses')->where('id', $course->id)->update(['university_id' => $universityId]);
            });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['university_id', 'code']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'courses.archive'],
            [
                'display_name' => 'Archive courses',
                'description' => 'Archive and restore course catalog records',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')->where('name', 'courses.archive')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['super_administrator', 'administrator', 'university_administrator', 'college_administrator', 'department_administrator'])
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
        $permissionId = DB::table('permissions')->where('name', 'courses.archive')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['university_id', 'code']);
            $table->unique('code');
            $table->dropColumn('status');
            $table->dropConstrainedForeignId('university_id');
        });
    }
};
