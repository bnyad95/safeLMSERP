<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('question_bank_questions');
    }

    public function down(): void
    {
        // Question bank was removed from the assessment workflow.
    }
};
