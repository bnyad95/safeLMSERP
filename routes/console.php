<?php

use App\Http\Controllers\AcademicYearClosureController;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\ClassMessage;
use App\Models\ClassStreamPost;
use App\Models\CourseMaterial;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ProtectedFileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:performance-warnings', function () {
    $warnings = app(NotificationService::class)->checkAndSendPerformanceWarnings();
    $this->info(count($warnings).' performance warning(s) processed.');
})->purpose('Send attendance and published-mark performance warnings');

Schedule::command('notifications:performance-warnings')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping();

Artisan::command('finance:refresh-overdue', function () {
    $updated = 0;

    FinanceTransaction::query()
        ->where('type', 'invoice')
        ->where('posting_status', 'posted')
        ->where('status', '!=', 'cancelled')
        ->whereIn('payment_status', ['open', 'partial', 'overdue'])
        ->withSum(['allocations as posted_credits' => function ($query) {
            $query->where('posting_status', 'posted')
                ->where('status', '!=', 'cancelled')
                ->whereIn('type', FinanceTransaction::creditTypes());
        }], 'amount')
        ->chunkById(250, function ($invoices) use (&$updated) {
            foreach ($invoices as $invoice) {
                $paid = (float) ($invoice->posted_credits ?? 0);
                $status = match (true) {
                    $paid >= (float) $invoice->amount => 'paid',
                    $invoice->due_date && $invoice->due_date->isPast() => 'overdue',
                    $paid > 0 => 'partial',
                    default => 'open',
                };

                if ($invoice->payment_status !== $status) {
                    $invoice->update(['payment_status' => $status]);
                    $updated++;
                }
            }
        });

    $this->info("Refreshed {$updated} tuition invoice status(es).");
})->purpose('Refresh paid, partial, open, and overdue tuition invoice statuses');

Schedule::command('finance:refresh-overdue')
    ->dailyAt('00:10')
    ->withoutOverlapping();

Artisan::command('files:migrate-protected', function () {
    $files = app(ProtectedFileService::class);
    $migrated = 0;
    $definitions = [
        [AssessmentItem::class, 'instruction_file_path'],
        [AssessmentSubmission::class, 'attachment_path'],
        [ClassMessage::class, 'attachment_path'],
        [ClassStreamPost::class, 'attachment_path'],
        [CourseMaterial::class, 'file_path'],
    ];

    foreach ($definitions as [$model, $column]) {
        $model::withoutGlobalScopes()
            ->whereNotNull($column)
            ->select(['id', $column])
            ->chunkById(250, function ($records) use ($files, $column, &$migrated) {
                foreach ($records as $record) {
                    $migrated += $files->migrateLegacyFile($record->{$column}) ? 1 : 0;
                }
            });
    }

    $this->info("Protected {$migrated} file(s). Files already private were also checked for public duplicates.");
})->purpose('Move legacy LMS attachments from public storage to protected storage');

Artisan::command('academic-years:rebuild-archive {academic_year? : Rebuild one academic year, for example 2026/2027} {--force : Rebuild even when a snapshot already exists}', function (?string $academic_year = null) {
    $result = app(AcademicYearClosureController::class)
        ->rebuildArchiveSummaries($academic_year, (bool) $this->option('force'));

    $this->info("Archive rebuild checked {$result['years']} academic year(s).");
    $this->line("Rebuilt closures: {$result['rebuilt']}");
    $this->line("Skipped closures: {$result['skipped']}");
})->purpose('Backfill or rebuild academic year archive snapshots for closed years');

Artisan::command('safelms:create-super-admin {email : Login email for the first administrator} {--name=Super Administrator : Administrator display name}', function (string $email) {
    $email = strtolower(trim($email));
    $name = trim((string) $this->option('name'));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');

        return 1;
    }

    if ($name === '') {
        $this->error('The administrator name cannot be empty.');

        return 1;
    }

    $password = (string) $this->secret('Password (minimum 12 characters)');
    $confirmation = (string) $this->secret('Confirm password');

    if (strlen($password) < 12) {
        $this->error('The password must contain at least 12 characters.');

        return 1;
    }

    if (! hash_equals($password, $confirmation)) {
        $this->error('The passwords do not match.');

        return 1;
    }

    if (! Role::where('name', 'super_administrator')->exists()) {
        $this->call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);
    }

    $user = User::withTrashed()->firstOrNew(['email' => $email]);
    $user->fill([
        'name' => $name,
        'password' => $password,
        'must_change_password' => false,
        'account_blocked_at' => null,
        'account_blocked_by' => null,
        'account_block_reason' => null,
    ]);
    $user->email_verified_at = now();
    $user->deleted_at = null;
    $user->save();

    $role = Role::where('name', 'super_administrator')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$role->id]);

    $this->info("Super Administrator ready: {$email}");

    return 0;
})->purpose('Create or restore the first production Super Administrator safely');
