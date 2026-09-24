<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $this->user()?->can('update', $patient) ?? false;
    }

    public function rules(): array
    {
        return [
            'providerId' => ['nullable', 'uuid', 'exists:providers,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'emrId' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'employer' => ['nullable', 'string', 'max:255'],
            'doi' => ['nullable', 'date'],
            'claimNum' => ['nullable', 'string', 'max:255'],
            'reportStatus' => ['sometimes', 'string', 'max:30'],
        ];
    }
}
