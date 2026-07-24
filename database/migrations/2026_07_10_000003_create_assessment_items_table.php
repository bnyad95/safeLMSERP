<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('assignment');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('allow_submissions')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_section_id', 'status']);
            $table->index(['type', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_items');
    }
};
