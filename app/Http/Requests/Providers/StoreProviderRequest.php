<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Provider::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'clinic' => ['required', 'uuid', 'exists:clinics,id'],
            'userId' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'npi' => ['required', 'string', 'size:10', 'unique:providers,npi'],
            'license' => ['required', 'string', 'max:50'],
            'specialty' => ['required', 'string', 'max:100'],
            'sigBlock' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
