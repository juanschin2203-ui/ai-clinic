<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Phi\PhiCreated;
use App\Events\Phi\PhiDeleted;
use App\Events\Phi\PhiUpdated;
use App\Models\AbstractRocketModel;
use Illuminate\Support\Facades\Event;

/**
 * Abstract observer that fires the corresponding Phi event on every write
 * lifecycle hook. Subclass (or register directly) for each PHI model.
 *
 * Does NOT observe the `retrieved` event — that would emit an audit row
 * for every Eloquent fetch including internal tenant-scoped empties. PHI
 * *reads* are instead emitted explicitly at the controller level via
 * `Event::dispatch(new PhiAccessed(...))`. That way we audit user-visible
 * reads, not framework internals.
 *
 * Tenant-scope semantics: the observer picks up the clinic UUID from the
 * current TenantContext OR the model's own tenant column, whichever is
 * set. For background jobs (no HTTP context), tenant resolution falls
 * back to the model's column so audit is always scoped.
 */
class PhiObserver
{
    public function created(AbstractRocketModel $model): void
    {
        Event::dispatch(new PhiCreated(
            model: $model,
            after: $model->getAttributes(),
            userId: $this->currentUserId(),
            clinicId: $this->tenantFor($model),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
            requestId: request()?->header('X-Request-Id'),
        ));
    }

    public function updated(AbstractRocketModel $model): void
    {
        $changed = $model->getChanges();
        if (empty($changed)) {
            return;
        }

        $before = array_intersect_key($model->getOriginal(), $changed);

        Event::dispatch(new PhiUpdated(
            model: $model,
            before: $before,
            after: $changed,
            userId: $this->currentUserId(),
            clinicId: $this->tenantFor($model),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
            requestId: request()?->header('X-Request-Id'),
        ));
    }

    public function deleting(AbstractRocketModel $model): void
    {
        // Hard delete if the model doesn't use SoftDeletes OR if forceDelete
        // is in progress. Laravel sets isForceDeleting() accordingly.
        $hard = ! method_exists($model, 'isForceDeleting') || $model->isForceDeleting();

        Event::dispatch(new PhiDeleted(
            model: $model,
            before: $model->getAttributes(),
            hardDelete: $hard,
            userId: $this->currentUserId(),
            clinicId: $this->tenantFor($model),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
            requestId: request()?->header('X-Request-Id'),
        ));
    }

    private function currentUserId(): ?string
    {
        return auth()->user()?->id;
    }

    /**
     * Best-effort tenant resolution — checks the model's tenant column first,
     * falls back to TenantContext.
     */
    private function tenantFor(AbstractRocketModel $model): ?string
    {
        if (method_exists($model, 'getTenantColumn')) {
            $col = $model::getTenantColumn();
            $val = $model->getAttribute($col);
            if (! empty($val)) {
                return (string) $val;
            }
        }

        return app(\App\Services\Tenancy\TenantContext::class)->getClinicId();
    }
}
