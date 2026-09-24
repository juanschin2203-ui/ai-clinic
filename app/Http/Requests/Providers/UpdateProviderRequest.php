<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $provider = $this->route('provider');

        return $this->user()?->can('update', $provider) ?? false;
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->id;

        return [
            'userId' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'npi' => ['sometimes', 'string', 'size:10', Rule::unique('providers', 'npi')->ignore($providerId)],
            'license' => ['sometimes', 'string', 'max:50'],
            'specialty' => ['sometimes', 'string', 'max:100'],
            'sigBlock' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
