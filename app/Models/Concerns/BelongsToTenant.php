<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Apply to every Eloquent model whose rows belong to a specific clinic.
 *
 * Effects:
 *   1. Registers TenantScope as a global scope — all queries auto-filter
 *      by the current tenant.
 *   2. On create(), auto-populates the tenant FK column from the
 *      TenantContext if the caller didn't set it explicitly.
 *
 * Default tenant FK column is `clinicId`. Override via static $tenantColumn
 * on the model (Provider, MedicalCase, ClinicAdmin use `clinic`).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope(static::getTenantColumn()));

        static::creating(function (Model $model): void {
            $ctx = app(TenantContext::class);

            if ($ctx->isBypassed() || ! $ctx->hasTenant()) {
                return;
            }

            $column = static::getTenantColumn();

            if (empty($model->{$column})) {
                $model->{$column} = $ctx->getClinicId();
            }
        });
    }

    /**
     * The FK column that holds this model's clinic UUID.
     * Override in the model (not an abstract method — trait can't enforce that).
     */
    public static function getTenantColumn(): string
    {
        return property_exists(static::class, 'tenantColumn')
            ? static::$tenantColumn
            : 'clinicId';
    }
}
