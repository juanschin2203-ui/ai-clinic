<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;

/**
 * Singleton that holds the current tenant's clinic UUID for the lifetime of a
 * request or queued job. Populated by auth middleware (Step 4) and read by
 * the TenantScope global scope on every tenant-scoped Eloquent query.
 *
 * Rocket-admin and deployer users have no tenant — setClinicId() is never
 * called for them, and queries against tenant-scoped models skip filtering
 * only when bypass() is explicitly invoked. That is intentional: silent
 * "no tenant set = no filter" would be a HIPAA footgun. For admins, use
 * runWithoutTenant() to make the cross-tenant query explicit and auditable.
 */
class TenantContext
{
    protected ?string $clinicId = null;

    protected bool $bypassed = false;

    public function setClinicId(?string $id): void
    {
        $this->clinicId = $id;
    }

    public function getClinicId(): ?string
    {
        return $this->clinicId;
    }

    public function hasTenant(): bool
    {
        return $this->clinicId !== null;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Run a callback with the tenant scope disabled. Use for admin cross-tenant
     * operations and system jobs. Restores the previous bypass state on exit
     * even if the callback throws.
     */
    public function runWithoutTenant(Closure $fn): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $fn();
        } finally {
            $this->bypassed = $previous;
        }
    }

    /**
     * Run a callback AS a specific tenant. Useful for admins operating on
     * behalf of a clinic. Restores the previous tenant on exit.
     */
    public function runAsTenant(string $clinicId, Closure $fn): mixed
    {
        $previousId = $this->clinicId;
        $previousBypass = $this->bypassed;

        $this->clinicId = $clinicId;
        $this->bypassed = false;

        try {
            return $fn();
        } finally {
            $this->clinicId = $previousId;
            $this->bypassed = $previousBypass;
        }
    }

    public function reset(): void
    {
        $this->clinicId = null;
        $this->bypassed = false;
    }
}
