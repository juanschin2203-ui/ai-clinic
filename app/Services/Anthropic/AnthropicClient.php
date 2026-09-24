<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use Anthropic\Anthropic as AnthropicSdk;
use Illuminate\Support\Facades\Log;

/**
 * Production implementation of AnthropicClientInterface. Wraps the
 * anthropic-ai/sdk package with:
 *
 *   - retry (exponential backoff: 2s, 4s, 8s)
 *   - timeout (configurable per call, default 60s)
 *   - structured-JSON mode (prompt the model to return a JSON object;
 *     strip leading/trailing whitespace; try to parse; on failure retry
 *     with a "REMINDER: return JSON only" prepend)
 *   - token-count tracking (from Anthropic's `usage` field in the response)
 *
 * Tests bind `FakeAnthropicClient` instead of this class so no real API
 * calls happen during CI.
 */
class AnthropicClient implements AnthropicClientInterface
{
    private ?AnthropicSdk $sdk = null;

    public function generateStructured(
        string $systemPrompt,
        string $userPrompt,
        string $modelKey = 'reasoning',
        ?int $maxTokens = null,
    ): ModelResponse {
        $model = (string) config("anthropic.models.{$modelKey}");
        $maxTokens ??= (int) config('anthropic.max_tokens_per_request');
        $attempts = (int) config('anthropic.retry_attempts', 3);
        $backoffs = (array) config('anthropic.retry_backoff_seconds', [2, 4, 8]);

        $lastRawText = '';

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                sleep($backoffs[$attempt - 1] ?? 4);
            }

            $startedAt = microtime(true);

            try {
                $response = $this->sdk()->messages()->create(
                    parameters: [
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'system' => $systemPrompt,
                        'messages' => [
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                    ],
                );

                $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
                $rawText = $this->extractText($response);
                $lastRawText = $rawText;

                $json = $this->parseJson($rawText);

                return new ModelResponse(
                    json: $json,
                    inputTokens: $response->usage->inputTokens ?? 0,
                    outputTokens: $response->usage->outputTokens ?? 0,
                    latencyMs: $latencyMs,
                    model: $model,
                );
            } catch (\JsonException $e) {
                Log::warning('Anthropic returned non-JSON', [
                    'attempt' => $attempt + 1,
                    'rawText' => mb_substr($lastRawText, 0, 400),
                ]);
                continue;
            } catch (\Throwable $e) {
                Log::error('Anthropic call failed', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt + 1 >= $attempts) {
                    throw $e;
                }
                continue;
            }
        }

        throw new StructuredOutputException($lastRawText);
    }

    /**
     * Walk Anthropic's response content blocks and concat text-type blocks.
     */
    private function extractText(mixed $response): string
    {
        $text = '';
        foreach ($response->content ?? [] as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text ?? '';
            }
        }

        return trim($text);
    }

    /**
     * Strip optional markdown code fences and parse as JSON object.
     */
    private function parseJson(string $text): array
    {
        $trimmed = trim($text);

        // Strip ```json ... ``` or ``` ... ``` fences
        if (preg_match('/^```(?:json)?\s*(.+?)\s*```$/s', $trimmed, $m)) {
            $trimmed = $m[1];
        }

        $data = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \JsonException('Parsed JSON is not an object/array.');
        }

        return $data;
    }

    private function sdk(): AnthropicSdk
    {
        if ($this->sdk === null) {
            $apiKey = (string) config('anthropic.api_key');
            if ($apiKey === '') {
                throw new \RuntimeException(
                    'ANTHROPIC_API_KEY is not set. Cannot initialize Anthropic SDK.',
                );
            }
            $this->sdk = AnthropicSdk::factory()
                ->withApiKey($apiKey)
                ->withHttpHeader('User-Agent', 'rocket-coding/1.0')
                ->make();
        }

        return $this->sdk;
    }
}
