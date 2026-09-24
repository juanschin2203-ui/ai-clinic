<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('tenant:clinicId')` — for routes with
 * a `{clinicId}` path parameter. Asserts that the path-param clinic ID
 * matches the authenticated user's cid.
 *
 * Redundant with the Eloquent global scope for query-level isolation,
 * but catches the "attacker tries /api/clinics/{otherClinicId}/..." case
 * at the HTTP layer before any query runs. Belt-and-suspenders against
 * the security invariant.
 *
 * Admins (role=admin or deployer) bypass this check — they legitimately
 * need to operate on other clinics' data via admin endpoints.
 */
class RequireTenantMatch
{
    public function handle(Request $request, Closure $next, string $param = 'clinicId'): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $routeClinicId = $request->route($param);
        if ($routeClinicId === null) {
            // Route is missing the expected parameter. Fail closed.
            abort(500, "Route parameter '{$param}' is missing.");
        }

        // Admins and deployers may operate cross-tenant.
        if (in_array($user->role?->value, ['admin', 'deployer'], strict: true)) {
            return $next($request);
        }

        if ($user->cid !== $routeClinicId) {
            abort(403, 'You do not have access to this clinic.');
        }

        return $next($request);
    }
}
