<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->enum('submission_status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft')->after('final_mark');
            $table->enum('visibility_status', ['draft', 'published'])->default('draft')->after('submission_status');
            $table->text('reviewer_notes')->nullable()->after('visibility_status');
            $table->timestamp('submitted_at')->nullable()->after('reviewer_notes');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->timestamp('published_at')->nullable()->after('reviewed_at');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('published_at');

            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['submission_status', 'visibility_status', 'reviewer_notes', 'submitted_at', 'reviewed_at', 'published_at', 'reviewed_by']);
        });
    }
};
