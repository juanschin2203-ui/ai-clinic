<?php

declare(strict_types=1);

namespace App\Events\Ai;

use Illuminate\Foundation\Events\Dispatchable;

class AiPipelineCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $caseId,
        public readonly string $clinicId,
        public readonly int $tokensUsed,
        public readonly float $overallConfidence,
        /** @var array<string, array{tokens: int, confidence: float|null}> */
        public readonly array $perStage,
    ) {}
}
