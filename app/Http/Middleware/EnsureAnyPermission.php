<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->hasRole('super_administrator') || $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        abort(403);
    }
}
