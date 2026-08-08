<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->boolean('prefinal_marks_open')->default(false)->after('end_date');
            $table->foreignId('prefinal_marks_opened_by')->nullable()->after('prefinal_marks_open')->constrained('users')->nullOnDelete();
            $table->timestamp('prefinal_marks_opened_at')->nullable()->after('prefinal_marks_opened_by');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prefinal_marks_opened_by');
            $table->dropColumn(['prefinal_marks_open', 'prefinal_marks_opened_at']);
        });
    }
};
