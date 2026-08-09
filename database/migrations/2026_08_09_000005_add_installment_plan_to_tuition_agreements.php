<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuition_agreements', function (Blueprint $table) {
            $table->unsignedInteger('installment_count')->nullable()->after('total_amount');
            $table->unsignedInteger('installments_generated')->default(0)->after('installment_count');
            $table->decimal('installment_amount', 14, 2)->nullable()->after('installments_generated');
        });
    }

    public function down(): void
    {
        Schema::table('tuition_agreements', function (Blueprint $table) {
            $table->dropColumn(['installment_count', 'installments_generated', 'installment_amount']);
        });
    }
};
