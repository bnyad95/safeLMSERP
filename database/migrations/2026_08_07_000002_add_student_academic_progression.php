<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('current_stage_id')->nullable()->after('department_id')->constrained('stages')->nullOnDelete();
            $table->string('academic_standing', 30)->default('new')->after('status');
            $table->index(['current_stage_id', 'academic_standing']);
        });

        Schema::create('student_stage_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->foreignId('next_stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->string('academic_year', 20);
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('outcome', 30);
            $table->decimal('attempted_credits', 8, 2)->default(0);
            $table->decimal('earned_credits', 8, 2)->default(0);
            $table->unsignedSmallInteger('failed_modules')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year', 'stage_id'], 'student_stage_year_unique');
            $table->index(['academic_year', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_stage_progressions');

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['current_stage_id', 'academic_standing']);
            $table->dropConstrainedForeignId('current_stage_id');
            $table->dropColumn('academic_standing');
        });
    }
};
