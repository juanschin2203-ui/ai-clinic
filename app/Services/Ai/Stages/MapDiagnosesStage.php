<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\ConfidenceScorer;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;
use App\Services\Anthropic\AnthropicClientInterface;

/**
 * Like SuggestCptStage, anti-hallucination defense via ConfidenceScorer.
 * Diagnoses without verifiable citations are rejected.
 *
 * Also enforces "exactly one primary DX" — coerces the model's output
 * if it returns 0 or >1 primaries (first code becomes primary by default).
 */
class MapDiagnosesStage extends AbstractStage
{
    public function __construct(
        AnthropicClientInterface $client,
        private readonly ConfidenceScorer $scorer,
    ) {
        parent::__construct($client);
    }

    public function name(): string
    {
        return 'mapDiagnoses';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::MAP_DIAGNOSES,
                userPrompt: $this->userPrompt($case),
            );

            $raw = (array) ($json['diagnoses'] ?? []);
            $notes = (string) ($case->notes ?? '');

            $verified = [];
            $scores = [];

            foreach ($raw as $item) {
                if (! is_array($item) || empty($item['code']) || empty($item['citation'])) {
                    continue;
                }

                $score = $this->scorer->scoreCodeSuggestion($item, $notes);
                if ($score < 0.50) {
                    continue;
                }

                $verified[] = [
                    'code' => (string) $item['code'],
                    'desc' => (string) ($item['desc'] ?? ''),
                    'primary' => (bool) ($item['primary'] ?? false),
                    'citation' => (string) $item['citation'],
                    'confidence' => round($score, 3),
                ];
                $scores[] = $score;
            }

            $this->enforceSinglePrimary($verified);

            $case->forceFill(['suggestedDX' => $verified])->save();

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $verified === [] ? 0.0 : $this->scorer->aggregate($scores),
            );
        });
    }

    /** Mutates the list in place so exactly one entry has primary=true. */
    private function enforceSinglePrimary(array &$diagnoses): void
    {
        if ($diagnoses === []) {
            return;
        }

        $primaryCount = count(array_filter($diagnoses, fn ($d) => ! empty($d['primary'])));

        if ($primaryCount === 1) {
            return;
        }

        // Zero primaries → promote the first. >1 primaries → keep only the first.
        foreach ($diagnoses as $i => &$d) {
            $d['primary'] = $i === 0;
        }
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
