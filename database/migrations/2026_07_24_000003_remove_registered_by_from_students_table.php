<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'registered_by')) {
                $table->dropConstrainedForeignId('registered_by');
            }
        });
    }

    public function down(): void
    {
        // Student visibility is department-scoped, not registrar-owned.
    }
};
