<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('permission:coder,full')`.
 *
 * Checks the authenticated user's `permission` value (Permission enum:
 * full / coder / scheduler / viewer). User must hold AT LEAST ONE of
 * the permissions passed.
 *
 * Admins and deployers bypass (their role already grants everything).
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$allowedPermissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (in_array($user->role?->value, ['admin', 'deployer'], strict: true)) {
            return $next($request);
        }

        if (! in_array($user->permission?->value, $allowedPermissions, strict: true)) {
            abort(403, 'This endpoint requires one of the following permissions: '.implode(', ', $allowedPermissions));
        }

        return $next($request);
    }
}
