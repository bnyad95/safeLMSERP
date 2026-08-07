<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->foreignId('final_exam_entered_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('final_exam_entered_at')->nullable()->after('final_exam_entered_by');
        });

        Schema::create('mark_score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trial', 20);
            $table->decimal('previous_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->string('previous_submission_status', 30);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['mark_id', 'created_at']);
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->insertOrIgnore([
                'name' => 'marks.enter_final_exam',
                'display_name' => 'Enter final exam marks',
                'description' => 'Enter first-trial and eligible second-trial final exam scores.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permissionId = DB::table('permissions')->where('name', 'marks.enter_final_exam')->value('id');
            $committeeRoleId = DB::table('roles')->where('name', 'examination_committee')->value('id');
            if ($permissionId && $committeeRoleId && Schema::hasTable('permission_role')) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $committeeRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', 'marks.enter_final_exam')->value('id');
            if ($permissionId && Schema::hasTable('permission_user')) {
                DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            }
            if ($permissionId && Schema::hasTable('permission_role')) {
                DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            }
            if ($permissionId) {
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        Schema::dropIfExists('mark_score_histories');

        Schema::table('marks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('final_exam_entered_by');
            $table->dropColumn('final_exam_entered_at');
        });
    }
};
