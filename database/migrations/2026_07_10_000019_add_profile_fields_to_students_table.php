<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('admission_status')->default('Admitted')->after('status');
            $table->date('admission_date')->nullable()->after('admission_status');
            $table->string('admission_type')->nullable()->after('admission_date');
            $table->string('previous_school')->nullable()->after('admission_type');
            $table->text('address')->nullable()->after('previous_school');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'admission_status',
                'admission_date',
                'admission_type',
                'previous_school',
                'address',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
            ]);
        });
    }
};
