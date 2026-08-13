<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tuition_rates', function (Blueprint $table) {
            $table->decimal('rate_per_credit', 12, 2)->nullable()->change();
            $table->decimal('flat_amount', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tuition_rates', function (Blueprint $table) {
            $table->decimal('rate_per_credit', 10, 2)->nullable()->change();
            $table->decimal('flat_amount', 10, 2)->nullable()->change();
        });
    }
};
