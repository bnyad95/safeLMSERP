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
        // Add soft deletes to users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to students table
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (! Schema::hasColumn('students', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to courses table
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (! Schema::hasColumn('courses', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to teachers table
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                if (! Schema::hasColumn('teachers', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to marks table
        if (Schema::hasTable('marks')) {
            Schema::table('marks', function (Blueprint $table) {
                if (! Schema::hasColumn('marks', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to departments table
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (! Schema::hasColumn('departments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('marks', function (Blueprint $table) {
            if (Schema::hasColumn('marks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
