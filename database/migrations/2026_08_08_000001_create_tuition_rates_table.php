<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('rate_per_credit', 10, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'academic_year_id', 'currency'], 'tuition_rates_dept_year_currency_unique');
            $table->index('university_id');
            $table->index('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_rates');
    }
};
