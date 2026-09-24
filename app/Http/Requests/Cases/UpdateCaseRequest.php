<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use App\Enums\CaseStatus;
use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $this->user()?->can('update', $case) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient' => ['sometimes', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'dos' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'claimNum' => ['nullable', 'string', 'max:50'],
            'emrId' => ['nullable', 'string', 'max:50'],
            'reportType' => ['sometimes', Rule::enum(ReportType::class)],
            'status' => ['sometimes', Rule::enum(CaseStatus::class)],
            'assignedCoderId' => ['nullable', 'uuid', 'exists:users,id'],
            'svcs' => ['sometimes', 'array'],
            'svcs.*' => ['string', 'exists:services,id'],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'string'],
            'suggestedCPT' => ['sometimes', 'array'],
            'suggestedDX' => ['sometimes', 'array'],
            'modifiers' => ['sometimes', 'array'],
        ];
    }
}
