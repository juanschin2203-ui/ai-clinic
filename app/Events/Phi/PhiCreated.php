<?php

declare(strict_types=1);

namespace App\Events\Phi;

use App\Models\AbstractRocketModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the Phi observer on every `Model::created` hook for a PHI model.
 * Carries the newly-created row's attribute snapshot.
 */
class PhiCreated
{
    use Dispatchable;

    public function __construct(
        public readonly AbstractRocketModel $model,
        public readonly array $after,
        public readonly ?string $userId,
        public readonly ?string $clinicId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $requestId = null,
    ) {}
}
