<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function email(): string
    {
        return strtolower(trim($this->string('email')->toString()));
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
