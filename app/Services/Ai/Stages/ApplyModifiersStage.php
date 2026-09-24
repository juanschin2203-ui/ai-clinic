<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;

class ApplyModifiersStage extends AbstractStage
{
    public function name(): string
    {
        return 'applyModifiers';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            $suggestedCpt = collect($case->suggestedCPT ?? [])
                ->map(fn ($c) => ['code' => $c['code'] ?? null, 'desc' => $c['desc'] ?? null])
                ->filter(fn ($c) => $c['code'] !== null)
                ->values()
                ->all();

            if ($suggestedCpt === []) {
                // No codes to apply modifiers to — skip with zero-length output.
                $case->forceFill(['modifiers' => []])->save();

                return StageResult::ok(tokens: 0, confidence: 1.0);
            }

            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::APPLY_MODIFIERS,
                userPrompt: $this->userPrompt($case, $suggestedCpt),
            );

            $modifiers = [];
            foreach ((array) ($json['modifiers'] ?? []) as $m) {
                if (empty($m['code']) || empty($m['appliedTo'])) {
                    continue;
                }
                $modifiers[] = [
                    'code' => (string) $m['code'],
                    'desc' => (string) ($m['desc'] ?? ''),
                    'appliedTo' => (string) $m['appliedTo'],
                    'stateRule' => $m['stateRule'] ?? null,
                ];
            }

            $case->forceFill(['modifiers' => $modifiers])->save();

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $this->safeConfidence($json['confidence'] ?? null),
            );
        });
    }

    /** @param array<int, array{code: string, desc: string|null}> $cpts */
    private function userPrompt(MedicalCase $case, array $cpts): string
    {
        $cptList = collect($cpts)->map(fn ($c) => "- {$c['code']} — {$c['desc']}")->implode("\n");

        return <<<USER
STATE: {$case->state?->value}
REPORT TYPE: {$case->reportType?->label()}

CPT CODES SUGGESTED:
{$cptList}

CLINICIAN NOTES:

{$case->notes}
USER;
    }
}
