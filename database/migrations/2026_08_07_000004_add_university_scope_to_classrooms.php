<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('university_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->dropUnique(['name']);
        });

        $roomUniversities = DB::table('timetables')
            ->join('course_sections', 'course_sections.id', '=', 'timetables.course_section_id')
            ->join('courses', 'courses.id', '=', 'course_sections.course_id')
            ->join('departments', 'departments.id', '=', 'courses.department_id')
            ->whereNotNull('timetables.classroom_id')
            ->select('timetables.classroom_id', 'departments.university_id')
            ->distinct()
            ->get()
            ->groupBy('classroom_id');

        foreach ($roomUniversities as $classroomId => $rows) {
            $universityIds = $rows->pluck('university_id')->filter()->unique();
            if ($universityIds->isEmpty()) {
                continue;
            }

            $originalUniversityId = $universityIds->shift();
            DB::table('classrooms')->where('id', $classroomId)->update([
                'university_id' => $originalUniversityId,
            ]);

            $originalRoom = DB::table('classrooms')->where('id', $classroomId)->first();
            foreach ($universityIds as $universityId) {
                $replacementRoomId = DB::table('classrooms')->insertGetId([
                    'university_id' => $universityId,
                    'name' => $originalRoom->name,
                    'building' => $originalRoom->building,
                    'capacity' => $originalRoom->capacity,
                    'type' => $originalRoom->type,
                    'status' => $originalRoom->status,
                    'notes' => $originalRoom->notes,
                    'created_at' => $originalRoom->created_at,
                    'updated_at' => $originalRoom->updated_at,
                ]);
                $sectionIds = DB::table('course_sections')
                    ->join('courses', 'courses.id', '=', 'course_sections.course_id')
                    ->join('departments', 'departments.id', '=', 'courses.department_id')
                    ->where('departments.university_id', $universityId)
                    ->pluck('course_sections.id');
                DB::table('timetables')
                    ->where('classroom_id', $classroomId)
                    ->whereIn('course_section_id', $sectionIds)
                    ->update(['classroom_id' => $replacementRoomId]);
            }
        }

        $onlyUniversityId = DB::table('universities')->count() === 1
            ? DB::table('universities')->value('id')
            : null;
        if ($onlyUniversityId) {
            DB::table('classrooms')->whereNull('university_id')->update(['university_id' => $onlyUniversityId]);
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unique(['university_id', 'name']);
        });
    }

    public function down(): void
    {
        $duplicateRooms = DB::table('classrooms')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateRooms as $roomName) {
            $roomIds = DB::table('classrooms')
                ->where('name', $roomName)
                ->orderBy('id')
                ->pluck('id');
            $roomToKeep = $roomIds->shift();

            DB::table('timetables')
                ->whereIn('classroom_id', $roomIds)
                ->update(['classroom_id' => $roomToKeep]);
            DB::table('classrooms')->whereIn('id', $roomIds)->delete();
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['university_id', 'name']);
            $table->unique('name');
            $table->dropConstrainedForeignId('university_id');
        });
    }
};
