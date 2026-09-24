<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $this->user()?->can('update', $appointment) ?? false;
    }

    public function rules(): array
    {
        return [
            'patientId' => ['nullable', 'uuid', 'exists:patients,id'],
            'providerId' => ['nullable', 'uuid', 'exists:providers,id'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i:s'],
            'patient' => ['sometimes', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'visitType' => ['sometimes', Rule::enum(ReportType::class)],
            'claim' => ['nullable', 'string', 'max:50'],
            'dictation' => ['sometimes', 'boolean'],
            'chartUploaded' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['scheduled', 'checkedIn', 'complete', 'noshow', 'cancelled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('time') && preg_match('/^\d{2}:\d{2}$/', (string) $this->input('time'))) {
            $this->merge(['time' => $this->input('time').':00']);
        }
    }
}
