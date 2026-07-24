<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_blocked_at')->nullable()->after('must_change_password');
            $table->foreignId('account_blocked_by')->nullable()->after('account_blocked_at')->constrained('users')->nullOnDelete();
            $table->text('account_block_reason')->nullable()->after('account_blocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_blocked_by']);
            $table->dropColumn(['account_blocked_at', 'account_blocked_by', 'account_block_reason']);
        });
    }
};
