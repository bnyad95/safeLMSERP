<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('department_id')->constrained()->nullOnDelete();
        });

        DB::table('students')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->each(function ($student) {
                $userId = DB::table('users')->where('email', $student->email)->value('id');

                if ($userId) {
                    DB::table('students')->where('id', $student->id)->update(['user_id' => $userId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
