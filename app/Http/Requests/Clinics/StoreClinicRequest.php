<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinics;

use App\Enums\UsState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Clinic::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2', Rule::enum(UsState::class)],
            'email' => ['required', 'email', 'max:255', 'unique:clinics,email'],
            'active' => ['sometimes', 'boolean'],
            'city' => ['nullable', 'string', 'max:255'],
            'addr' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'npi' => ['nullable', 'string', 'max:15'],
            'taxId' => ['nullable', 'string', 'max:15'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'selfCoded' => ['sometimes', 'boolean'],
            'reportFavs' => ['nullable', 'array'],
            'reportFavs.*' => ['string'],
            'notif' => ['nullable', 'array'],
        ];
    }
}
