<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('update', $target) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'permission' => ['sometimes', Rule::enum(Permission::class)],
            'active' => ['sometimes', 'boolean'],
            'initials' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
