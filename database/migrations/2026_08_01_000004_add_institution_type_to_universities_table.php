<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('universities', 'institution_type')) {
            Schema::table('universities', function (Blueprint $table) {
                $table->string('institution_type', 20)->default('university')->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('universities', 'institution_type')) {
            Schema::table('universities', function (Blueprint $table) {
                $table->dropColumn('institution_type');
            });
        }
    }
};
