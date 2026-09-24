<?php

declare(strict_types=1);

namespace App\Events\Ai;

use Illuminate\Foundation\Events\Dispatchable;

class AiPipelineFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $caseId,
        public readonly string $clinicId,
        public readonly string $failedStageName,
        public readonly string $errorCode,       // 'parse_failed' | 'api_error' | 'token_budget_exceeded' | ...
        public readonly string $errorMessage,
        public readonly int $tokensUsed,
    ) {}
}
