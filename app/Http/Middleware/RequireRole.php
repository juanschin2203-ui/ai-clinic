<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('role:admin,deployer')`.
 *
 * Accepts one or more role values; the authenticated user's role must
 * match AT LEAST ONE of them. Any other role → 403.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (! in_array($user->role?->value, $allowedRoles, strict: true)) {
            abort(403, 'This endpoint requires one of the following roles: '.implode(', ', $allowedRoles));
        }

        return $next($request);
    }
}
