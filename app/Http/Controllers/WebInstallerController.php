<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class WebInstallerController extends Controller
{
    public function show(): View
    {
        $this->ensureInstallationIsAvailable();

        return view('installer.index');
    }

    public function install(Request $request): RedirectResponse
    {
        $this->ensureInstallationIsAvailable();

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $validated = $request->validate([
            'installation_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $expectedToken = (string) config('installer.token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $validated['installation_code'])) {
            return back()
                ->withInput($request->except(['installation_code', 'password', 'password_confirmation']))
                ->withErrors(['installation_code' => 'The installation code is invalid.']);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', [
                '--class' => RolePermissionSeeder::class,
                '--force' => true,
            ]);

            $user = User::withTrashed()->firstOrNew(['email' => $validated['email']]);
            $user->fill([
                'name' => $validated['name'],
                'password' => $validated['password'],
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

            Storage::disk('local')->put('installed', json_encode([
                'installed_at' => now()->toIso8601String(),
                'user_id' => $user->id,
            ], JSON_PRETTY_PRINT));

            Auth::login($user);
            $request->session()->regenerate();
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['installation_code', 'password', 'password_confirmation']))
                ->withErrors([
                    'installer' => 'Installation could not finish. Verify the cPanel database settings and storage permissions, then try again.',
                ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'SafeLMS installation completed successfully.');
    }

    private function ensureInstallationIsAvailable(): void
    {
        abort_unless(config('installer.enabled') && ! $this->installationIsComplete(), 404);
    }

    private function installationIsComplete(): bool
    {
        if (Storage::disk('local')->exists('installed')) {
            return true;
        }

        try {
            if (! Schema::hasTable('users')
                || ! Schema::hasTable('roles')
                || ! Schema::hasTable('role_user')) {
                return false;
            }

            return User::whereHas('roles', fn ($query) => $query->where('name', 'super_administrator'))->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
