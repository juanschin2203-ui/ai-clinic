<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

interface AnthropicClientInterface
{
    /**
     * Send a message with a system prompt and parse the response as JSON.
     *
     * @param  string        $systemPrompt   high-level instructions ("you are a WC coding assistant...")
     * @param  string        $userPrompt     the actual task payload (clinician notes, state, etc.)
     * @param  string        $modelKey       'extraction' | 'reasoning' — resolves to config('anthropic.models.*')
     * @param  int|null      $maxTokens      override per-request cap; null = config default
     *
     * @throws StructuredOutputException     if 3 retries all fail to return parsable JSON
     */
    public function generateStructured(
        string $systemPrompt,
        string $userPrompt,
        string $modelKey = 'reasoning',
        ?int $maxTokens = null,
    ): ModelResponse;
}
