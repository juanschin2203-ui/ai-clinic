<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * What a stage tells the PipelineOrchestrator once it's done.
 *
 *   $tokensUsed  — total (input + output) Anthropic tokens consumed
 *   $confidence  — stage-level confidence [0..1]; the orchestrator writes
 *                  the MIN across all stages onto the case
 *   $errorCode   — for failure escalation: 'parse_failed' / 'low_confidence'
 *                  / 'api_error' etc.
 */
final class StageResult
{
    public function __construct(
        public readonly bool $succeeded,
        public readonly int $tokensUsed,
        public readonly ?float $confidence = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function ok(int $tokens, ?float $confidence = null): self
    {
        return new self(true, $tokens, $confidence);
    }

    public static function fail(string $code, string $message, int $tokensUsed = 0): self
    {
        return new self(false, $tokensUsed, null, $code, $message);
    }
}
