<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method', 20);
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3)->default('IQD');
            $table->string('status', 20)->default('active');
            $table->date('agreed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['academic_year_id', 'payment_method']);
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->foreignId('tuition_agreement_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->after('tuition_agreement_id')->constrained()->nullOnDelete();
            $table->string('posting_status', 20)->default('posted')->after('status');
            $table->index(['student_id', 'currency', 'posting_status'], 'finance_student_currency_posting_index');
            $table->index(['tuition_agreement_id', 'semester_id'], 'finance_agreement_semester_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_block_type', 30)->nullable()->after('account_block_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_block_type');
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_student_currency_posting_index');
            $table->dropIndex('finance_agreement_semester_index');
            $table->dropConstrainedForeignId('semester_id');
            $table->dropConstrainedForeignId('tuition_agreement_id');
            $table->dropColumn('posting_status');
        });

        Schema::dropIfExists('tuition_agreements');
    }
};
