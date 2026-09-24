<?php

declare(strict_types=1);

use App\Exceptions\Auth\AccountInactiveException;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidRefreshTokenException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RequireClinic;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireTenantMatch;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Attach / propagate X-Request-Id on every request for audit correlation.
        $middleware->api(prepend: [AssignRequestId::class]);

        // Rocket Coding middleware aliases. Used on routes like
        // `->middleware(['auth:sanctum', 'tenant-context', 'role:admin'])`.
        $middleware->alias([
            'tenant-context' => SetTenantContext::class,
            'role' => RequireRole::class,
            'clinic' => RequireClinic::class,
            'tenant' => RequireTenantMatch::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Auth exceptions → structured JSON responses with appropriate HTTP codes.
        $exceptions->render(function (InvalidCredentialsException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'The email or password you entered is incorrect.',
                ],
            ], 401);
        });

        $exceptions->render(function (AccountInactiveException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'account_inactive',
                    'message' => 'This account has been deactivated. Contact your clinic admin or Rocket Coding support.',
                ],
            ], 403);
        });

        $exceptions->render(function (AccountLockedException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'account_locked',
                    'message' => 'Too many failed attempts. Try again later.',
                    'retryAfterSeconds' => $e->secondsUntilUnlock,
                ],
            ], 423);
        });

        $exceptions->render(function (InvalidRefreshTokenException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_refresh_token',
                    'message' => 'Your session has expired. Please log in again.',
                ],
            ], 401);
        });
    })->create();
