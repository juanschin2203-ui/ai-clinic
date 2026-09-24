<?php

declare(strict_types=1);

namespace App\Services\Ai\Stages;

use App\Models\MedicalCase;
use App\Services\Ai\Prompts;
use App\Services\Ai\StageResult;

class ExtractPatientDataStage extends AbstractStage
{
    public function name(): string
    {
        return 'extractPatientData';
    }

    /** Cheap stage — run on Haiku. */
    protected function modelKey(): string
    {
        return 'extraction';
    }

    public function run(MedicalCase $case): StageResult
    {
        return $this->guard(function () use ($case) {
            [$json, $in, $out] = $this->callModel(
                systemPrompt: Prompts::EXTRACT_PATIENT_DATA,
                userPrompt: $this->userPrompt($case),
                maxTokens: 512,
            );

            $updates = [];
            if (! empty($json['patientName']) && empty($case->patient)) {
                $updates['patient'] = (string) $json['patientName'];
            }
            if (! empty($json['dob']) && empty($case->dob)) {
                $updates['dob'] = (string) $json['dob'];
            }
            if (! empty($json['dateOfService']) && empty($case->dos)) {
                $updates['dos'] = (string) $json['dateOfService'];
            }
            if (! empty($json['gender']) && empty($case->gender)) {
                $updates['gender'] = (string) $json['gender'];
            }
            if (! empty($json['claimNumber']) && empty($case->claimNum)) {
                $updates['claimNum'] = (string) $json['claimNumber'];
            }

            if ($updates !== []) {
                $case->forceFill($updates)->save();
            }

            return StageResult::ok(
                tokens: $in + $out,
                confidence: $this->safeConfidence($json['confidence'] ?? null),
            );
        });
    }

    private function userPrompt(MedicalCase $case): string
    {
        return "CLINICIAN NOTES:\n\n".($case->notes ?? '(no notes on file)');
    }
}
