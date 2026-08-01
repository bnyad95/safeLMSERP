<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('graduation_credits')->nullable()->after('passing_credits');
        });

        DB::table('semester_credit_policies')
            ->join('universities', 'universities.id', '=', 'semester_credit_policies.university_id')
            ->select('semester_credit_policies.id', 'semester_credit_policies.semester_credits', 'universities.expected_stage_count', 'universities.expected_semesters_per_year')
            ->orderBy('semester_credit_policies.id')
            ->get()
            ->each(function ($policy) {
                DB::table('semester_credit_policies')
                    ->where('id', $policy->id)
                    ->update([
                        'graduation_credits' => (int) $policy->semester_credits
                            * max(1, (int) $policy->expected_stage_count)
                            * max(1, (int) $policy->expected_semesters_per_year),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->dropColumn('graduation_credits');
        });
    }
};
