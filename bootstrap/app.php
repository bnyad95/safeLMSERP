<?php

use App\Http\Middleware\EnsureAccountIsNotBlocked;
use App\Http\Middleware\EnsureAnyPermission;
use App\Http\Middleware\EnsureAnyRole;
use App\Http\Middleware\EnsureAnyRoleOrDirectPermission;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureAccountIsNotBlocked::class,
            EnsurePasswordIsChanged::class,
        ]);

        $middleware->alias([
            'access.any' => EnsureAnyRoleOrDirectPermission::class,
            'permission.any' => EnsureAnyPermission::class,
            'role.any' => EnsureAnyRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->guest(route('login'));
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please log in again.'], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('status', 'Your session has expired. Please log in again.');
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            return redirect()
                ->guest(route('login'))
                ->with('status', 'Your session has expired. Please log in again.');
        });
    })->create();
