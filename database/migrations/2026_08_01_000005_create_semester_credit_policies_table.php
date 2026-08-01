<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_credit_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('semester_credits');
            $table->unsignedSmallInteger('passing_credits');
            $table->timestamps();

            $table->unique('university_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_credit_policies');
    }
};
