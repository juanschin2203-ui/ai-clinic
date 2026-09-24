<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates the TenantContext singleton from the authenticated user's `cid`.
 * Runs AFTER auth:sanctum so `$request->user()` is available.
 *
 * This is where architectural tenant isolation gets its clinic UUID — once
 * set, every Eloquent query against a BelongsToTenant model auto-filters
 * by this tenant. Impossible to forget in a controller; impossible to bypass
 * without explicitly calling TenantContext::runWithoutTenant().
 *
 * Admins and Deployers have `cid = null` — they operate cross-tenant, so
 * TenantContext stays unset for them. Any tenant-scoped query they issue
 * returns all rows (the global scope no-ops when no tenant is set). This
 * is the intended behavior: admin console endpoints should NOT be filtered.
 *
 * For clinic/provider/staff roles: enforces `cid` must be present. If an
 * authenticated clinic user somehow has no cid set on their row, we abort
 * with 403 — this is a data integrity bug, and defaulting to "no tenant =
 * see everything" would be a HIPAA incident.
 */
class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $ctx,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $role = $user->role?->value;
        $cid = $user->cid;

        // Clinic / provider / staff MUST have a clinic. Enforce hard.
        if (in_array($role, ['clinic', 'provider', 'staff'], strict: true)) {
            if (empty($cid)) {
                abort(403, 'User is not assigned to a clinic.');
            }

            $this->ctx->setClinicId($cid);
        }

        // Admin / deployer: leave tenant unset. Cross-tenant reads are
        // their job; code must opt-in with explicit filters.

        return $next($request);
    }
}
