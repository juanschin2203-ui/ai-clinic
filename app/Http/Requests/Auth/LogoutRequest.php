<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refreshToken' => ['nullable', 'string', 'min:32', 'max:128'],
        ];
    }

    public function refreshToken(): ?string
    {
        $val = $this->string('refreshToken')->toString();

        return $val === '' ? null : $val;
    }
}
