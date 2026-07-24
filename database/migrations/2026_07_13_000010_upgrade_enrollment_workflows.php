<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->text('drop_reason')->nullable()->after('dropped_at');
            $table->timestamp('waitlisted_at')->nullable()->after('drop_reason');
            $table->foreignId('transferred_from_id')->nullable()->after('waitlisted_at')->constrained('enrollments')->nullOnDelete();
        });

        Schema::create('enrollment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['course_section_id', 'occurred_at']);
            $table->index(['student_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_events');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferred_from_id');
            $table->dropColumn(['drop_reason', 'waitlisted_at']);
        });
    }
};
