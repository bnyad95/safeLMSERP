<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->unique()->after('status');
            $table->string('receipt_number')->nullable()->unique()->after('invoice_number');
            $table->decimal('balance_after', 12, 2)->nullable()->after('amount');
            $table->string('payment_status')->default('open')->after('status');

            $table->index(['student_id', 'payment_status']);
            $table->index(['invoice_number', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'payment_status']);
            $table->dropIndex(['invoice_number', 'receipt_number']);
            $table->dropUnique(['invoice_number']);
            $table->dropUnique(['receipt_number']);
            $table->dropColumn(['invoice_number', 'receipt_number', 'balance_after', 'payment_status']);
        });
    }
};
