<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnyRoleOrDirectPermission
{
    public function handle(Request $request, Closure $next, string ...$rules): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        foreach ($rules as $rule) {
            [$type, $value] = array_pad(explode(':', $rule, 2), 2, null);

            if ($type === 'role' && $value && $user->hasRole($value)) {
                return $next($request);
            }

            if ($type === 'permission' && $value && $user->hasDirectPermissionGrant($value)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
