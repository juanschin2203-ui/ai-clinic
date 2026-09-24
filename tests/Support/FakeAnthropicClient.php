<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Anthropic\AnthropicClientInterface;
use App\Services\Anthropic\ModelResponse;
use App\Services\Anthropic\StructuredOutputException;

/**
 * Test double for AnthropicClientInterface. Lets a test queue up scripted
 * responses per stage name, assert which stages were called, and force
 * failure modes (structured-output exceptions, arbitrary throws).
 *
 * Usage:
 *   $fake = new FakeAnthropicClient();
 *   $fake->forStage('suggestCpt', ['codes' => [...]]);
 *   $fake->forStage('mapDiagnoses', ['diagnoses' => [...]]);
 *   $this->app->instance(AnthropicClientInterface::class, $fake);
 *
 * Stage detection: the fake identifies which stage is calling it by
 * inspecting the FIRST LINE of the system prompt (each prompt starts
 * with a distinctive phrase: "You are a medical data extraction system.",
 * "You are a medical-legal report writer", etc.).
 */
class FakeAnthropicClient implements AnthropicClientInterface
{
    /** @var array<string, array> */
    private array $scriptedResponses = [];

    /** @var array<string, \Throwable> */
    private array $scriptedFailures = [];

    /** @var array<int, array{stageKey: string, systemPrompt: string, userPrompt: string, modelKey: string}> */
    public array $calls = [];

    /**
     * Queue a JSON response for a specific stage key. See `stageKeyFor()` for
     * the mapping from prompt content to stage name.
     */
    public function forStage(string $stageKey, array $jsonResponse): self
    {
        $this->scriptedResponses[$stageKey] = $jsonResponse;

        return $this;
    }

    /**
     * Force a specific stage to throw (e.g., StructuredOutputException) to
     * test failure escalation.
     */
    public function failStage(string $stageKey, \Throwable $error): self
    {
        $this->scriptedFailures[$stageKey] = $error;

        return $this;
    }

    public function generateStructured(
        string $systemPrompt,
        string $userPrompt,
        string $modelKey = 'reasoning',
        ?int $maxTokens = null,
    ): ModelResponse {
        $stageKey = $this->stageKeyFor($systemPrompt);

        $this->calls[] = [
            'stageKey' => $stageKey,
            'systemPrompt' => $systemPrompt,
            'userPrompt' => $userPrompt,
            'modelKey' => $modelKey,
        ];

        if (isset($this->scriptedFailures[$stageKey])) {
            throw $this->scriptedFailures[$stageKey];
        }

        if (! isset($this->scriptedResponses[$stageKey])) {
            throw new \RuntimeException(
                "FakeAnthropicClient was called for stage [{$stageKey}] but no response was scripted. ".
                'Add ->forStage("'.$stageKey.'", [...]) in the test setup.'
            );
        }

        return new ModelResponse(
            json: $this->scriptedResponses[$stageKey],
            inputTokens: 500,
            outputTokens: 300,
            latencyMs: 50,
            model: $modelKey === 'extraction' ? 'claude-haiku-4-5' : 'claude-sonnet-4-6',
        );
    }

    /**
     * Walk the stage prompts to figure out which stage is calling us.
     * Returns the same string the stage's `name()` method returns.
     */
    private function stageKeyFor(string $systemPrompt): string
    {
        $firstLine = mb_substr($systemPrompt, 0, 200);

        return match (true) {
            str_contains($firstLine, 'medical data extraction system') => 'extractPatientData',
            str_contains($firstLine, 'medical-legal report writer') => 'draftReport',
            str_contains($firstLine, 'CPT coding assistant') => 'suggestCpt',
            str_contains($firstLine, 'CPT modifier specialist') => 'applyModifiers',
            str_contains($firstLine, 'ICD-10-CM coding assistant') => 'mapDiagnoses',
            str_contains($firstLine, 'state-specific Workers') => 'checkCompliance',
            default => 'unknown',
        };
    }

    public function calledStages(): array
    {
        return array_column($this->calls, 'stageKey');
    }

    public function throwStructuredOutput(string $stageKey, string $rawText = '{ not json'): self
    {
        return $this->failStage($stageKey, new StructuredOutputException($rawText));
    }
}
