<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('is_retake')->default(false)->after('status');
            $table->foreignId('retake_from_enrollment_id')->nullable()->after('is_retake')->constrained('enrollments')->nullOnDelete();
            $table->string('retake_reason')->nullable()->after('retake_from_enrollment_id');

            $table->index(['student_id', 'is_retake']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'is_retake']);
            $table->dropConstrainedForeignId('retake_from_enrollment_id');
            $table->dropColumn(['is_retake', 'retake_reason']);
        });
    }
};
