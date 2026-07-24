<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function requireAnyPermission(string ...$permissions): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasRole('super_administrator') || $user->hasAnyPermission($permissions)) {
            return;
        }

        abort(403);
    }

    protected function requireAnyRole(string ...$roles): void
    {
        $user = auth()->user();

        abort_unless($user, 403);
        abort_unless($user->hasAnyRole($roles), 403);
    }

    protected function requireAnyRoleOrDirectPermission(array $roles, array $permissions): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasAnyRole($roles) || $user->hasAnyDirectPermissionGrant($permissions)) {
            return;
        }

        abort(403);
    }
}
