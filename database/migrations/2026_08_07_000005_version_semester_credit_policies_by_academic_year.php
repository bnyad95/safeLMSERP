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
            $table->index('university_id', 'semester_credit_policies_university_id_index');
        });

        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->dropUnique('semester_credit_policies_university_id_unique');
            $table->foreignId('academic_year_id')->nullable()->after('university_id')->constrained()->cascadeOnDelete();
        });

        DB::table('semester_credit_policies')->orderBy('id')->get()->each(function ($policy) {
            $yearIds = DB::table('academic_years')
                ->where('university_id', $policy->university_id)
                ->orderBy('starts_on')
                ->orderBy('id')
                ->pluck('id');

            if ($yearIds->isEmpty()) {
                return;
            }

            DB::table('semester_credit_policies')->where('id', $policy->id)->update([
                'academic_year_id' => $yearIds->shift(),
            ]);

            foreach ($yearIds as $yearId) {
                DB::table('semester_credit_policies')->insert([
                    'university_id' => $policy->university_id,
                    'academic_year_id' => $yearId,
                    'semester_credits' => $policy->semester_credits,
                    'passing_credits' => $policy->passing_credits,
                    'graduation_credits' => $policy->graduation_credits,
                    'created_at' => $policy->created_at,
                    'updated_at' => $policy->updated_at,
                ]);
            }
        });

        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->unique(['university_id', 'academic_year_id'], 'credit_policy_university_year_unique');
        });
    }

    public function down(): void
    {
        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->dropUnique('credit_policy_university_year_unique');
        });

        DB::table('semester_credit_policies')
            ->whereNotNull('academic_year_id')
            ->orderBy('id')
            ->get()
            ->groupBy('university_id')
            ->each(function ($policies) {
                DB::table('semester_credit_policies')->whereIn('id', $policies->skip(1)->pluck('id'))->delete();
            });

        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
            $table->unique('university_id');
        });

        Schema::table('semester_credit_policies', function (Blueprint $table) {
            $table->dropIndex('semester_credit_policies_university_id_index');
        });
    }
};
