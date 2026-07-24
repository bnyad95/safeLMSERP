<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->string('grade_level')->nullable()->after('section_code');
            $table->index(['grade_level', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropIndex(['grade_level', 'semester_id']);
            $table->dropColumn('grade_level');
        });
    }
};
