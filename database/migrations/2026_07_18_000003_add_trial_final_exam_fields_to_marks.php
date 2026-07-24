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
            $table->decimal('first_trial_final_exam', 5, 2)->nullable()->after('prefinal_mark');
            $table->decimal('second_trial_final_exam', 5, 2)->nullable()->after('first_trial_final_exam');
        });

        DB::table('marks')
            ->where('final_exam', '>', 0)
            ->update(['first_trial_final_exam' => DB::raw('final_exam')]);
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn(['first_trial_final_exam', 'second_trial_final_exam']);
        });
    }
};
