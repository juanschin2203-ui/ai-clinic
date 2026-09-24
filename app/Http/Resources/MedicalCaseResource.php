<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MedicalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX CASES (line 75):
 *   id, num, patient, dob, dos, gender, claimNum, emrId, status, svcs,
 *   total, clinic, clinicName, state, reportType, inputSource, notes,
 *   suggestedCPT, suggestedDX, modifiers, stateCompliance, emails,
 *   audit, timeline.
 *
 * `status` is returned as the JSX-style display string (not the enum key)
 * because the frontend renders it directly in Badge pills. A separate
 * `statusKey` field carries the machine value for new frontend code that
 * wants it.
 *
 * @property-read MedicalCase $resource
 */
class MedicalCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MedicalCase $case */
        $case = $this->resource;

        return [
            'id' => $case->id,
            'num' => $case->num,
            'patient' => $case->patient,
            'patientId' => $case->patientId,
            'providerId' => $case->providerId,
            'assignedCoderId' => $case->assignedCoderId,
            'dob' => $case->dob?->toDateString(),
            'dos' => $case->dos?->toDateString(),
            'gender' => $case->gender,
            'claimNum' => $case->claimNum,
            'emrId' => $case->emrId,
            'status' => $case->status?->label(),       // display string, matches JSX
            'statusKey' => $case->status?->value,      // machine value for new code
            'svcs' => $case->svcs ?? [],
            'total' => $case->total,
            'clinic' => $case->clinic,
            'clinicName' => $case->clinicName,
            'state' => $case->state?->value,
            'reportType' => $case->reportType?->value,
            'inputSource' => $case->inputSource?->value,
            'notes' => $case->notes,
            'suggestedCPT' => $case->suggestedCPT ?? [],
            'suggestedDX' => $case->suggestedDX ?? [],
            'modifiers' => $case->modifiers ?? [],
            'stateCompliance' => $case->stateCompliance ?? (object) [],
            'emails' => $case->emails ?? [],
            'audit' => $case->audit ?? [],
            'timeline' => $case->timeline ?? [],
            'aiTokensUsed' => $case->aiTokensUsed,
            'aiTokenBudget' => $case->aiTokenBudget,
            'aiCompletedAt' => $case->aiCompletedAt?->toIso8601String(),
            'deliveredAt' => $case->deliveredAt?->toIso8601String(),
            'createdAt' => $case->createdAt?->toIso8601String(),
            'updatedAt' => $case->updatedAt?->toIso8601String(),
        ];
    }
}
