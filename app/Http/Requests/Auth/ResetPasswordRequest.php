<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'min:32', 'max:128'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function email(): string
    {
        return strtolower(trim($this->string('email')->toString()));
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}
