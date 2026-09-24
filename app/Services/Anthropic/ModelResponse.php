<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

/**
 * Immutable DTO returned from AnthropicClient::generateStructured().
 *
 *   $json         — the already-parsed response payload (matches the
 *                   schema the stage asked for)
 *   $inputTokens  — prompt tokens Anthropic billed
 *   $outputTokens — completion tokens Anthropic billed
 *   $latencyMs    — wall-clock latency of the API call
 *   $model        — actual model string used (so we can audit the cheap
 *                   vs reasoning split)
 */
final class ModelResponse
{
    public function __construct(
        public readonly array $json,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $latencyMs,
        public readonly string $model,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
