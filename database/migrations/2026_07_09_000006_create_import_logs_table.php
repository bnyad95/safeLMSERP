<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // students, marks
            $table->string('filename');
            $table->integer('total_rows')->default(0);
            $table->integer('successful')->default(0);
            $table->integer('failed')->default(0);
            $table->json('errors')->nullable();
            $table->unsignedBigInteger('imported_by');
            $table->timestamps();

            $table->foreign('imported_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
