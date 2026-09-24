<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Appointment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'clinicId' => ['required', 'uuid', 'exists:clinics,id'],
            'patientId' => ['nullable', 'uuid', 'exists:patients,id'],
            'providerId' => ['nullable', 'uuid', 'exists:providers,id'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i:s'],
            'patient' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'visitType' => ['required', Rule::enum(ReportType::class)],
            'claim' => ['nullable', 'string', 'max:50'],
            'dictation' => ['sometimes', 'boolean'],
            'chartUploaded' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('clinicId') && $this->user()?->cid !== null) {
            $this->merge(['clinicId' => $this->user()->cid]);
        }

        // Laravel's `date_format:H:i:s` rejects "08:00" — normalize to "08:00:00".
        if ($this->has('time') && preg_match('/^\d{2}:\d{2}$/', (string) $this->input('time'))) {
            $this->merge(['time' => $this->input('time').':00']);
        }
    }
}
