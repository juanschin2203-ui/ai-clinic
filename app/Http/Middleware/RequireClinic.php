<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('clinic')`.
 *
 * Ensures the authenticated user belongs to a clinic. Admins (cid=null)
 * are rejected — this is for clinic-scoped endpoints only. If an admin
 * needs to operate on a specific clinic, they use the cross-tenant
 * admin endpoints (Step 5) which accept `?clinicId=` explicitly.
 */
class RequireClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (empty($user->cid)) {
            abort(403, 'This endpoint is clinic-scoped; cross-tenant users must use admin routes.');
        }

        return $next($request);
    }
}
