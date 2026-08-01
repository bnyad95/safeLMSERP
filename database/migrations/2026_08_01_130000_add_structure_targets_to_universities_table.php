<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('universities', function (Blueprint $table) {
            $table->unsignedSmallInteger('expected_stage_count')->default(4)->after('institution_type');
            $table->unsignedSmallInteger('expected_semesters_per_year')->default(2)->after('expected_stage_count');
        });

        DB::table('universities')
            ->where('institution_type', 'institute')
            ->update(['expected_stage_count' => 2]);
    }

    public function down(): void
    {
        Schema::table('universities', function (Blueprint $table) {
            $table->dropColumn(['expected_stage_count', 'expected_semesters_per_year']);
        });
    }
};
