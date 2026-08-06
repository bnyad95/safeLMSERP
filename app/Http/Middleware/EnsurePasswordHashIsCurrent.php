<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHashIsCurrent
{
    private const SESSION_KEY = 'auth.password_fingerprint';

    private const USER_SESSION_KEY = 'auth.password_fingerprint_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('logout')) {
            return $next($request);
        }

        $fingerprint = hash('sha256', $user->getAuthPassword());
        $sessionFingerprint = $request->session()->get(self::SESSION_KEY);
        $sessionUserId = $request->session()->get(self::USER_SESSION_KEY);

        if ($sessionFingerprint === null || (int) $sessionUserId !== (int) $user->getAuthIdentifier()) {
            $request->session()->put(self::SESSION_KEY, $fingerprint);
            $request->session()->put(self::USER_SESSION_KEY, $user->getAuthIdentifier());

            return $next($request);
        }

        if (hash_equals($sessionFingerprint, $fingerprint)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Your password changed. Please log in again.');
    }
}
