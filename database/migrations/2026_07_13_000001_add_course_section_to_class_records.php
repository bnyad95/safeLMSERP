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
            $table->foreignId('course_section_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->index(['course_section_id', 'student_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'student_id', 'date']);
            $table->foreignId('course_section_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->unique(['course_section_id', 'student_id', 'date'], 'attendance_section_student_date_unique');
        });

        Schema::table('course_materials', function (Blueprint $table) {
            $table->foreignId('course_section_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->index(['course_section_id', 'visibility']);
        });

        $this->backfillStudentClassRecords('marks');
        $this->backfillStudentClassRecords('attendances');

        DB::table('course_materials')->whereNull('course_section_id')->orderBy('id')->eachById(function ($material) {
            $sectionIds = DB::table('course_sections')
                ->where('course_id', $material->course_id)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($sectionIds->count() === 1) {
                DB::table('course_materials')->where('id', $material->id)->update(['course_section_id' => $sectionIds->first()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_materials', function (Blueprint $table) {
            $table->dropIndex(['course_section_id', 'visibility']);
            $table->dropConstrainedForeignId('course_section_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendance_section_student_date_unique');
            $table->dropConstrainedForeignId('course_section_id');
            $table->unique(['course_id', 'student_id', 'date']);
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->dropIndex(['course_section_id', 'student_id']);
            $table->dropConstrainedForeignId('course_section_id');
        });
    }

    private function backfillStudentClassRecords(string $table): void
    {
        DB::table($table)->whereNull('course_section_id')->orderBy('id')->eachById(function ($record) use ($table) {
            $sectionIds = DB::table('enrollments')
                ->join('course_sections', 'course_sections.id', '=', 'enrollments.course_section_id')
                ->where('enrollments.student_id', $record->student_id)
                ->where('enrollments.status', 'enrolled')
                ->where('course_sections.course_id', $record->course_id)
                ->whereNull('enrollments.deleted_at')
                ->whereNull('course_sections.deleted_at')
                ->pluck('course_sections.id');

            if ($sectionIds->count() === 1) {
                DB::table($table)->where('id', $record->id)->update(['course_section_id' => $sectionIds->first()]);
            }
        });
    }
};
