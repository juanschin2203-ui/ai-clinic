<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX PATIENTS (line 70):
 *   id, name, dob, emrId, phone, employer, doi, provider (display string),
 *   reportStatus.
 * Backend adds: clinicId, providerId, gender, email, claimNum.
 *
 * `provider` is the provider's display name (string), computed from the
 * loaded provider relation so the JSX consumes it without change.
 *
 * @property-read Patient $resource
 */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Patient $patient */
        $patient = $this->resource;

        return [
            'id' => $patient->id,
            'clinicId' => $patient->clinicId,
            'providerId' => $patient->providerId,
            'name' => $patient->name,
            'dob' => $patient->dob?->toDateString(),
            'gender' => $patient->gender,
            'emrId' => $patient->emrId,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'employer' => $patient->employer,
            'doi' => $patient->doi?->toDateString(),
            'claimNum' => $patient->claimNum,
            'provider' => $patient->relationLoaded('provider')
                ? $patient->provider?->name
                : null,
            'reportStatus' => $patient->reportStatus,
        ];
    }
}
