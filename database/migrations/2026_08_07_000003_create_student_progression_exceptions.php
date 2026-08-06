<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progression_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->string('status', 30);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year'], 'student_progression_exception_year_unique');
            $table->index(['academic_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progression_exceptions');
    }
};
