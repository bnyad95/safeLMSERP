<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('short_answer');
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->string('difficulty')->default('medium');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'type', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_questions');
    }
};
