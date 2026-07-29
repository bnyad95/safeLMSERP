<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitialMigrationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_organization_tables_are_available_with_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('universities'));
        $this->assertTrue(Schema::hasTable('departments'));
        $this->assertTrue(Schema::hasTable('students'));

        $migrationNames = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path))
            ->sort()
            ->values();

        $universities = $migrationNames->search('2026_07_08_094905_prepare_universities_table.php');
        $departments = $migrationNames->search('2026_07_08_094906_create_departments_table.php');

        $this->assertIsInt($universities);
        $this->assertIsInt($departments);
        $this->assertLessThan($departments, $universities);
    }

    public function test_attendance_foreign_keys_have_dedicated_indexes(): void
    {
        $migration = file_get_contents(
            database_path('migrations/2026_07_09_000004_create_attendances_table.php')
        );

        $this->assertStringContainsString("\$table->index('course_id');", $migration);
        $this->assertStringContainsString("\$table->index('student_id');", $migration);
    }
}
