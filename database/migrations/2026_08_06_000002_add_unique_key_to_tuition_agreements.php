<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuition_agreements', function (Blueprint $table) {
            $table->string('agreement_key', 64)->nullable()->unique()->after('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::table('tuition_agreements', function (Blueprint $table) {
            $table->dropUnique(['agreement_key']);
            $table->dropColumn('agreement_key');
        });
    }
};
