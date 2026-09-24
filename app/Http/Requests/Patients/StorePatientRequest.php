<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Patient::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'clinicId' => ['required', 'uuid', 'exists:clinics,id'],
            'providerId' => ['nullable', 'uuid', 'exists:providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'emrId' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'employer' => ['nullable', 'string', 'max:255'],
            'doi' => ['nullable', 'date'],
            'claimNum' => ['nullable', 'string', 'max:255'],
            'reportStatus' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clinic users don't need to pass clinicId — we populate it from their
        // current tenant. Admins must pass it explicitly.
        if (! $this->has('clinicId') && $this->user()?->cid !== null) {
            $this->merge(['clinicId' => $this->user()->cid]);
        }
    }
}
