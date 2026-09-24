<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;

class CheckComplianceStage extends AbstractStage
{
    public function name(): string
    {
        return 'checkCompliance';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::CHECK_COMPLIANCE,
                userPrompt: $this->userPrompt($case),
            );

            $compliance = [
                'compliant' => (bool) ($json['compliant'] ?? true),
                'system' => (string) ($json['system'] ?? 'Unknown'),
                'note' => (string) ($json['note'] ?? ''),
                'violations' => array_map(
                    fn ($v) => [
                        'rule' => (string) ($v['rule'] ?? ''),
                        'detail' => (string) ($v['detail'] ?? ''),
                    ],
                    (array) ($json['violations'] ?? []),
                ),
            ];

            $case->forceFill(['stateCompliance' => $compliance])->save();

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $this->safeConfidence($json['confidence'] ?? null),
            );
        });
    }

    private function userPrompt(MedicalCase $case): string
    {
        $cptList = collect($case->suggestedCPT ?? [])
            ->map(fn ($c) => "  - {$c['code']} — {$c['desc']}")
            ->implode("\n");

        $dxList = collect($case->suggestedDX ?? [])
            ->map(fn ($d) => "  - {$d['code']} — {$d['desc']}".(($d['primary'] ?? false) ? ' [primary]' : ''))
            ->implode("\n");

        $modList = collect($case->modifiers ?? [])
            ->map(fn ($m) => "  - {$m['code']} applied to {$m['appliedTo']} — {$m['desc']}")
            ->implode("\n");

        return <<<USER
STATE: {$case->state?->value}
REPORT TYPE: {$case->reportType?->label()}

CPT CODES:
{$cptList}

ICD-10 DIAGNOSES:
{$dxList}

MODIFIERS:
{$modList}

CLINICIAN NOTES:

{$case->notes}
USER;
    }
}
