<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;

/**
 * Drafts the report body sections from clinician notes. The required section
 * set comes from the ReportType enum (`requiredSections()`), computed per
 * case so the prompt tells the model exactly what headings to produce.
 *
 * Output lands in `cases.emails` as a rendering-ready draft? No — the draft
 * is stored under a dedicated JSON column if we add one. For Step 6 we
 * persist the sections array into the `audit` trail so it's visible; Step
 * 7's frontend work will pull it out. (Schema decision to revisit if the
 * frontend needs persistent access.)
 */
class DraftReportStage extends AbstractStage
{
    public function name(): string
    {
        return 'draftReport';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            $reportType = $case->reportType;
            $requiredSections = $reportType?->requiredSections() ?? [];

            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::DRAFT_REPORT,
                userPrompt: $this->userPrompt($case, $reportType?->label() ?? 'Unknown', $requiredSections),
            );

            // Persist sections alongside the audit trail so the coder can
            // see the generated draft in the case detail view.
            $audit = $case->audit ?? [];
            $audit[] = [
                'action' => 'AI Draft Report',
                'detail' => 'Drafted '.count($json['sections'] ?? []).' sections for '
                    .($reportType?->label() ?? 'report').'.',
                'ts' => now()->toDateString(),
                'payload' => ['sections' => $json['sections'] ?? []],
            ];

            $case->forceFill(['audit' => $audit])->save();

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $this->safeConfidence($json['confidence'] ?? null),
            );
        });
    }

    /**
     * @param string[] $requiredSections
     */
    private function userPrompt(MedicalCase $case, string $reportTypeLabel, array $requiredSections): string
    {
        $sectionList = implode("\n- ", $requiredSections ?: ['(none specified)']);

        return <<<USER
REPORT TYPE: {$reportTypeLabel}

REQUIRED SECTIONS (in this order):
- {$sectionList}

CLINICIAN NOTES:

{$case->notes}
USER;
    }
}
