<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_stream_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['course_section_id', 'created_at']);
        });

        Schema::create('class_stream_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_stream_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_stream_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_stream_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('like');
            $table->timestamps();
            $table->unique(['class_stream_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_stream_reactions');
        Schema::dropIfExists('class_stream_comments');
        Schema::dropIfExists('class_stream_posts');
    }
};
