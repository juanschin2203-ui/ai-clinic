<?php

declare(strict_types=1);

namespace App\Events\Phi;

use App\Models\AbstractRocketModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on Model::deleting (soft-delete → sets deletedAt; hard-delete → row gone).
 * The listener writes a "delete" audit entry with a snapshot of the row as it
 * was before deletion.
 */
class PhiDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly AbstractRocketModel $model,
        public readonly array $before,
        public readonly bool $hardDelete,
        public readonly ?string $userId,
        public readonly ?string $clinicId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $requestId = null,
    ) {}
}
