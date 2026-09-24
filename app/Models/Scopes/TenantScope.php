<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query against a tenant-scoped model to
 * the current TenantContext's clinicId. Added automatically by the
 * BelongsToTenant trait.
 *
 * Behavior by state:
 *   - tenant set + not bypassed -> appends WHERE {table}.{column} = ?
 *   - bypass active              -> no filter (admin cross-tenant explicit)
 *   - tenant NOT set             -> no filter (boot-time / seed / tinker —
 *                                   caller is responsible, there's no user
 *                                   context to filter by)
 *
 * The "no tenant, no filter" case would be a HIPAA footgun if reached during
 * a real request. Step 4's middleware asserts that every authenticated
 * clinic/provider/staff user has a clinicId set BEFORE any controller runs.
 */
class TenantScope implements Scope
{
    public function __construct(private readonly string $column) {}

    public function apply(Builder $builder, Model $model): void
    {
        $ctx = app(TenantContext::class);

        if ($ctx->isBypassed()) {
            return;
        }

        $clinicId = $ctx->getClinicId();

        if ($clinicId === null) {
            return;
        }

        $builder->where(
            $model->getTable() . '.' . $this->column,
            '=',
            $clinicId,
        );
    }
}
