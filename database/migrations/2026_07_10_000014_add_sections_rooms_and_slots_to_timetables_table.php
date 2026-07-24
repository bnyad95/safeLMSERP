<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->foreignId('course_section_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->after('teacher_id')->constrained()->nullOnDelete();
            $table->foreignId('time_slot_id')->nullable()->after('classroom_id')->constrained('timetable_time_slots')->nullOnDelete();
            $table->string('status')->default('scheduled')->after('type');

            $table->index(['day_of_week', 'start_time', 'end_time']);
            $table->index(['course_section_id', 'day_of_week']);
            $table->index(['teacher_id', 'day_of_week']);
            $table->index(['classroom_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropIndex(['day_of_week', 'start_time', 'end_time']);
            $table->dropIndex(['course_section_id', 'day_of_week']);
            $table->dropIndex(['teacher_id', 'day_of_week']);
            $table->dropIndex(['classroom_id', 'day_of_week']);
            $table->dropConstrainedForeignId('course_section_id');
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropConstrainedForeignId('time_slot_id');
            $table->dropColumn('status');
        });
    }
};
