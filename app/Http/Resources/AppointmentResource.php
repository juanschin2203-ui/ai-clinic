<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX APPOINTMENTS (line 33):
 *   id, date, time, patient, provider, company, visitType, claim,
 *   dictation, chartUploaded
 * Backend also returns clinicId / patientId / providerId so the UI can
 * link into detail views.
 *
 * @property-read Appointment $resource
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Appointment $appt */
        $appt = $this->resource;

        return [
            'id' => $appt->id,
            'clinicId' => $appt->clinicId,
            'patientId' => $appt->patientId,
            'providerId' => $appt->providerId,
            'date' => $appt->date?->toDateString(),
            'time' => $appt->time,
            'patient' => $appt->patient,
            'provider' => $appt->provider,
            'company' => $appt->company,
            'visitType' => $appt->visitType?->value,
            'claim' => $appt->claim,
            'dictation' => $appt->dictation,
            'chartUploaded' => $appt->chartUploaded,
            'status' => $appt->status,
        ];
    }
}
