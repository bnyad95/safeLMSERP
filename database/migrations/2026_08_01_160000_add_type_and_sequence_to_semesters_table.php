<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->string('term_type', 20)->default('regular')->after('name');
            $table->unsignedSmallInteger('sequence')->default(1)->after('term_type');
            $table->index(['academic_year_id', 'sequence']);
        });

        DB::table('semesters')
            ->select('id', 'university_id', 'academic_year', 'name')
            ->orderBy('university_id')
            ->orderBy('academic_year')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($semester) => $semester->university_id.'|'.$semester->academic_year)
            ->each(function ($semesters) {
                foreach ($semesters->values() as $index => $semester) {
                    DB::table('semesters')->where('id', $semester->id)->update([
                        'term_type' => str_contains(strtolower($semester->name), 'summer') ? 'summer' : 'regular',
                        'sequence' => $index + 1,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropIndex(['academic_year_id', 'sequence']);
            $table->dropColumn(['term_type', 'sequence']);
        });
    }
};
