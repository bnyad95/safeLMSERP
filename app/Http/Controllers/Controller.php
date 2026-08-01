<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function safeUploadRules(string $presence = 'nullable', int $maxKilobytes = 25600): array
    {
        return [
            $presence,
            'file',
            'max:'.$maxKilobytes,
            'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,mp4,mov,zip',
        ];
    }

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
