<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['student_id', 'transaction_date', 'id'], 'ft_student_txn_id_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('CREATE INDEX ft_student_curr_flow_idx ON finance_transactions (student_id, currency, type(32), status(24), payment_status(24))');
            DB::statement('CREATE INDEX ft_student_due_flow_idx ON finance_transactions (student_id, due_date, payment_status(24), status(24))');
            DB::statement('CREATE INDEX ft_year_currency_idx ON finance_transactions (academic_year(20), currency)');
            DB::statement('CREATE INDEX ft_type_status_pay_idx ON finance_transactions (type(32), status(24), payment_status(24))');

            return;
        }

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['student_id', 'currency', 'type', 'status', 'payment_status'], 'ft_student_curr_flow_idx');
            $table->index(['student_id', 'due_date', 'payment_status', 'status'], 'ft_student_due_flow_idx');
            $table->index(['academic_year', 'currency'], 'ft_year_currency_idx');
            $table->index(['type', 'status', 'payment_status'], 'ft_type_status_pay_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('ft_student_txn_id_idx');
            $table->dropIndex('ft_student_curr_flow_idx');
            $table->dropIndex('ft_student_due_flow_idx');
            $table->dropIndex('ft_year_currency_idx');
            $table->dropIndex('ft_type_status_pay_idx');
        });
    }
};
