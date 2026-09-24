<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\User;

class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;
    }

    public function view(User $user, Provider $provider): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->cid === $provider->clinic;
    }

    public function create(User $user): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->role === UserRole::Clinic && $user->permission?->value === 'full';
    }

    public function update(User $user, Provider $provider): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $provider->clinic
            && $user->permission?->value === 'full';
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $this->update($user, $provider);
    }
}
