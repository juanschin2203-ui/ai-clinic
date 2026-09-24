<?php

declare(strict_types=1);

namespace App\Events\Phi;

use App\Models\AbstractRocketModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the Phi observer on `Model::updating` (we capture `$model->getOriginal()`
 * before the save completes). Payload includes BEFORE and AFTER diffs so the
 * auditor can reconstruct what changed.
 */
class PhiUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly AbstractRocketModel $model,
        public readonly array $before,
        public readonly array $after,
        public readonly ?string $userId,
        public readonly ?string $clinicId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $requestId = null,
    ) {}
}
