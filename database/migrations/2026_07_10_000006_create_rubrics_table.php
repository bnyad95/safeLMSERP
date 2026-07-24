<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_item_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('criteria');
            $table->decimal('total_points', 8, 2)->default(100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('assessment_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubrics');
    }
};
