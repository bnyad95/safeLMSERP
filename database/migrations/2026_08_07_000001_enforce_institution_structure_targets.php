<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('universities')
            ->where('institution_type', 'institute')
            ->update([
                'expected_stage_count' => 2,
                'expected_semesters_per_year' => 2,
            ]);

        DB::table('universities')
            ->where('institution_type', '<>', 'institute')
            ->update([
                'institution_type' => 'university',
                'expected_stage_count' => 4,
                'expected_semesters_per_year' => 2,
            ]);
    }

    public function down(): void
    {
        // The previous custom targets cannot be reconstructed safely.
    }
};
