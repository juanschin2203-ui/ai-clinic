<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\ConfidenceScorer;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;
use App\Services\Anthropic\AnthropicClientInterface;

/**
 * THE anti-hallucination stage. Per the user's security invariant: every
 * suggested CPT code MUST carry a verbatim `citation` from the clinician
 * notes, and the citation must actually appear in the source text. Codes
 * whose citations can't be grounded are REJECTED and removed from the
 * output.
 *
 * Confidence per code is derived via ConfidenceScorer — model self-report
 * is only one signal, multiplicatively combined with structural checks.
 */
class SuggestCptStage extends AbstractStage
{
    public function __construct(
        AnthropicClientInterface $client,
        private readonly ConfidenceScorer $scorer,
    ) {
        parent::__construct($client);
    }

    public function name(): string
    {
        return 'suggestCpt';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::SUGGEST_CPT,
                userPrompt: $this->userPrompt($case),
            );

            $raw = (array) ($json['codes'] ?? []);
            $notes = (string) ($case->notes ?? '');

            $verified = [];
            $scores = [];

            foreach ($raw as $item) {
                if (! is_array($item) || empty($item['code']) || empty($item['citation'])) {
                    // Missing mandatory fields → reject.
                    continue;
                }

                $score = $this->scorer->scoreCodeSuggestion($item, $notes);
                if ($score < 0.50) {
                    // Grounded-citation check failed or too weak → reject.
                    continue;
                }

                $verified[] = [
                    'code' => (string) $item['code'],
                    'desc' => (string) ($item['desc'] ?? ''),
                    'reasoning' => (string) ($item['reasoning'] ?? ''),
                    'citation' => (string) $item['citation'],
                    'confidence' => round($score, 3),
                ];
                $scores[] = $score;
            }

            $case->forceFill(['suggestedCPT' => $verified])->save();

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $verified === [] ? 0.0 : $this->scorer->aggregate($scores),
            );
        });
    }

    private function userPrompt(MedicalCase $case): string
    {
        return <<<USER
REPORT TYPE: {$case->reportType?->label()}
STATE: {$case->state?->value}

CLINICIAN NOTES (verbatim):

{$case->notes}
USER;
    }
}
