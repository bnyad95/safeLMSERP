<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('university_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('college_id')->nullable()->after('university_id')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['university_id', 'college_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['college_id']);
            $table->dropForeign(['university_id']);
            $table->dropIndex(['university_id', 'college_id', 'department_id']);
            $table->dropColumn(['university_id', 'college_id', 'department_id']);
        });
    }
};
