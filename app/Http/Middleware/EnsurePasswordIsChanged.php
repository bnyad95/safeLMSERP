<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('password.force-edit', 'password.update', 'logout', 'verification.*')) {
            return $next($request);
        }

        return redirect()->route('password.force-edit');
    }
}
