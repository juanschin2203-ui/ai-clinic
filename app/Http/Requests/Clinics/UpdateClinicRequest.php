<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinics;

use App\Enums\UsState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        $clinic = $this->route('clinic');

        return $this->user()?->can('update', $clinic) ?? false;
    }

    public function rules(): array
    {
        $clinicId = $this->route('clinic')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'size:2', Rule::enum(UsState::class)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('clinics', 'email')->ignore($clinicId)],
            'active' => ['sometimes', 'boolean'],
            'city' => ['nullable', 'string', 'max:255'],
            'addr' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'npi' => ['nullable', 'string', 'max:15'],
            'taxId' => ['nullable', 'string', 'max:15'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'logo' => ['nullable', 'string', 'max:500'],
            'selfCoded' => ['sometimes', 'boolean'],
            'reportFavs' => ['nullable', 'array'],
            'reportFavs.*' => ['string'],
            'notif' => ['nullable', 'array'],
        ];
    }
}
