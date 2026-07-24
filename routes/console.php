<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AcademicYearClosureController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('academic-years:rebuild-archive {academic_year? : Rebuild one academic year, for example 2026/2027} {--force : Rebuild even when a snapshot already exists}', function (?string $academic_year = null) {
    $result = app(AcademicYearClosureController::class)
        ->rebuildArchiveSummaries($academic_year, (bool) $this->option('force'));

    $this->info("Archive rebuild checked {$result['years']} academic year(s).");
    $this->line("Rebuilt closures: {$result['rebuilt']}");
    $this->line("Skipped closures: {$result['skipped']}");
})->purpose('Backfill or rebuild academic year archive snapshots for closed years');
