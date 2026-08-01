<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['university_id', 'name']);
            $table->index(['status', 'name']);
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
            $table->unique(['department_id', 'sequence']);
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('university_id')
                ->constrained('academic_years')
                ->nullOnDelete();
        });

        Schema::table('course_sections', function (Blueprint $table) {
            $table->foreignId('stage_id')
                ->nullable()
                ->after('semester_id')
                ->constrained('stages')
                ->nullOnDelete();
        });

        $now = now();
        DB::table('semesters')
            ->selectRaw('university_id, academic_year, MIN(start_date) as starts_on, MAX(end_date) as ends_on')
            ->whereNotNull('academic_year')
            ->where('academic_year', '<>', '')
            ->groupBy('university_id', 'academic_year')
            ->orderBy('university_id')
            ->orderBy('academic_year')
            ->get()
            ->each(function ($period) use ($now) {
                $closed = DB::table('academic_year_closures')
                    ->where('university_id', $period->university_id)
                    ->where('academic_year', $period->academic_year)
                    ->where('status', 'closed')
                    ->exists();

                $academicYearId = DB::table('academic_years')->insertGetId([
                    'university_id' => $period->university_id,
                    'name' => $period->academic_year,
                    'starts_on' => $period->starts_on,
                    'ends_on' => $period->ends_on,
                    'status' => $closed ? 'closed' : 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('semesters')
                    ->where('university_id', $period->university_id)
                    ->where('academic_year', $period->academic_year)
                    ->update(['academic_year_id' => $academicYearId]);
            });

        $stageRows = DB::table('course_sections')
            ->join('courses', 'course_sections.course_id', '=', 'courses.id')
            ->join('departments', 'courses.department_id', '=', 'departments.id')
            ->whereNotNull('course_sections.grade_level')
            ->where('course_sections.grade_level', '<>', '')
            ->select('departments.id as department_id', 'departments.university_id', 'course_sections.grade_level')
            ->distinct()
            ->orderBy('departments.id')
            ->orderBy('course_sections.grade_level')
            ->get()
            ->groupBy('department_id');

        foreach ($stageRows as $departmentRows) {
            foreach ($departmentRows->values() as $index => $stageRow) {
                $stageId = DB::table('stages')->insertGetId([
                    'university_id' => $stageRow->university_id,
                    'department_id' => $stageRow->department_id,
                    'name' => $stageRow->grade_level,
                    'code' => null,
                    'sequence' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $sectionIds = DB::table('course_sections')
                    ->join('courses', 'course_sections.course_id', '=', 'courses.id')
                    ->where('courses.department_id', $stageRow->department_id)
                    ->where('course_sections.grade_level', $stageRow->grade_level)
                    ->pluck('course_sections.id');

                DB::table('course_sections')
                    ->whereIn('id', $sectionIds)
                    ->update(['stage_id' => $stageId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::dropIfExists('stages');
        Schema::dropIfExists('academic_years');
    }
};
