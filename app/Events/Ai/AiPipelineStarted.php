<?php

declare(strict_types=1);

namespace App\Events\Ai;

use Illuminate\Foundation\Events\Dispatchable;

class AiPipelineStarted
{
    use Dispatchable;

    public function __construct(
        public readonly string $caseId,
        public readonly string $clinicId,
    ) {}
}
