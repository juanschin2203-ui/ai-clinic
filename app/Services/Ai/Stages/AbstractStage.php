<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\StageResult;
use App\Services\Anthropic\AnthropicClientInterface;
use App\Services\Anthropic\StructuredOutputException;
use Illuminate\Support\Facades\Log;

/**
 * Base for every AI pipeline stage. Each concrete stage:
 *
 *   1. Defines its system prompt (Prompts::*)
 *   2. Builds a user prompt from the case
 *   3. Calls the Anthropic client to get structured JSON
 *   4. Validates the response shape (throws on bad output)
 *   5. Persists the result onto the MedicalCase
 *   6. Returns a StageResult with tokensUsed + confidence
 *
 * The PipelineOrchestrator is responsible for retry + token-budget
 * checks + moving the case to `needsReview` on repeated failure.
 */
abstract class AbstractStage
{
    public function __construct(
        protected readonly AnthropicClientInterface $client,
    ) {}

    abstract public function name(): string;

    abstract public function run(MedicalCase $case): StageResult;

    /** 'extraction' (Haiku) or 'reasoning' (Sonnet); default reasoning. */
    protected function modelKey(): string
    {
        return 'reasoning';
    }

    /**
     * Call Anthropic and wrap failures into a StageResult — so the
     * orchestrator can handle both "tried and failed cleanly" and
     * "tried and threw" symmetrically.
     *
     * @return array{0: array, 1: int, 2: int}  [jsonPayload, inputTokens, outputTokens]
     */
    protected function callModel(
        string $systemPrompt,
        string $userPrompt,
        ?int $maxTokens = null,
    ): array {
        $response = $this->client->generateStructured(
            $systemPrompt,
            $userPrompt,
            $this->modelKey(),
            $maxTokens,
        );

        Log::info('AI stage model response', [
            'stage' => $this->name(),
            'model' => $response->model,
            'inputTokens' => $response->inputTokens,
            'outputTokens' => $response->outputTokens,
            'latencyMs' => $response->latencyMs,
        ]);

        return [$response->json, $response->inputTokens, $response->outputTokens];
    }

    /**
     * Graceful-fail: catches StructuredOutputException and general throwables,
     * wraps into StageResult. Concrete stages use this in their run() method.
     */
    protected function guard(callable $fn): StageResult
    {
        try {
            return $fn();
        } catch (StructuredOutputException $e) {
            Log::warning('AI stage output parse failure', [
                'stage' => $this->name(),
                'rawText' => mb_substr($e->rawText, 0, 400),
            ]);

            return StageResult::fail(
                code: 'parse_failed',
                message: 'Model returned output that did not match the expected schema.',
            );
        } catch (\Throwable $e) {
            Log::error('AI stage threw', [
                'stage' => $this->name(),
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return StageResult::fail(
                code: 'api_error',
                message: $e->getMessage(),
            );
        }
    }

    /**
     * Number between 0 and 1, safely coerced from the model's self-reported
     * confidence (used only for stages where structural grounding isn't
     * available, e.g., compliance check with no citations to verify).
     */
    protected function safeConfidence(mixed $raw, float $default = 0.7): float
    {
        if (! is_numeric($raw)) {
            return $default;
        }

        return max(0.0, min(1.0, (float) $raw));
    }
}
