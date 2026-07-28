<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AcademicYearClosureController;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

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
