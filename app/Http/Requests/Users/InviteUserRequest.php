<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invite a new user. Role + clinic scope enforced by the policy; this
 * FormRequest only handles shape validation.
 */
class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'permission' => ['required', Rule::enum(Permission::class)],
            'cid' => ['nullable', 'uuid', 'exists:clinics,id'],
            'initials' => ['required', 'string', 'max:10'],
        ];
    }
}
