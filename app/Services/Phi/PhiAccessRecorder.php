<?php

declare(strict_types=1);

namespace App\Services\Phi;

use App\Events\Phi\PhiAccessed;
use App\Models\AbstractRocketModel;
use Illuminate\Support\Facades\Event;

/**
 * Tiny helper injected anywhere that needs to record a PHI read (controller
 * show methods, export endpoints, PDF generation). Centralizing the dispatch
 * keeps controllers thin and means future changes (batching, sampling)
 * happen in one place.
 */
class PhiAccessRecorder
{
    public function record(AbstractRocketModel $model): void
    {
        Event::dispatch(new PhiAccessed(
            model: $model,
            userId: auth()->user()?->id,
            clinicId: app(\App\Services\Tenancy\TenantContext::class)->getClinicId(),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
            requestId: request()?->header('X-Request-Id'),
        ));
    }
}
