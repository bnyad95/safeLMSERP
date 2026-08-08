<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_charge_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tuition_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('credits', 6, 1);
            $table->decimal('rate_per_credit', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_retake')->default(false);
            $table->timestamps();

            $table->unique(['finance_transaction_id', 'enrollment_id'], 'tuition_charge_lines_invoice_enrollment_unique');
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_charge_lines');
    }
};
