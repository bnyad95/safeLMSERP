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
        Schema::table('students', function (Blueprint $table) {
            $table->string('preferred_payment_method', 20)->nullable()->after('emergency_contact_phone');
            $table->unsignedInteger('preferred_installment_count')->nullable()->after('preferred_payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['preferred_payment_method', 'preferred_installment_count']);
        });
    }
};
