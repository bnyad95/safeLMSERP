<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->foreignId('invoice_transaction_id')->nullable()->after('student_id')->constrained('finance_transactions')->nullOnDelete();
            $table->foreignId('original_transaction_id')->nullable()->after('invoice_transaction_id')->constrained('finance_transactions')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('voided_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');

            $table->index(['student_id', 'currency']);
            $table->index(['invoice_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'currency']);
            $table->dropIndex(['invoice_transaction_id', 'status']);
            $table->dropConstrainedForeignId('invoice_transaction_id');
            $table->dropConstrainedForeignId('original_transaction_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};
