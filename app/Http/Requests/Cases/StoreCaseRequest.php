<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use App\Enums\InputSource;
use App\Enums\ReportType;
use App\Enums\UsState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\MedicalCase::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'clinic' => ['required', 'uuid', 'exists:clinics,id'],
            'patientId' => ['nullable', 'uuid', 'exists:patients,id'],
            'providerId' => ['nullable', 'uuid', 'exists:providers,id'],
            'patient' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'dos' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'claimNum' => ['nullable', 'string', 'max:50'],
            'emrId' => ['nullable', 'string', 'max:50'],
            'state' => ['required', 'string', 'size:2', Rule::enum(UsState::class)],
            'reportType' => ['required', Rule::enum(ReportType::class)],
            'inputSource' => ['required', Rule::enum(InputSource::class)],
            'svcs' => ['nullable', 'array'],
            'svcs.*' => ['string', 'exists:services,id'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('clinic') && $this->user()?->cid !== null) {
            $this->merge(['clinic' => $this->user()->cid]);
        }
    }
}
