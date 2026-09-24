<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;

/**
 * Services are reference data. Anyone authenticated can read the catalog.
 * Only admins/deployers can modify it.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;
    }

    public function view(User $user, Service $service): bool
    {
        return $user->role !== null;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }

    public function update(User $user, Service $service): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }

    public function delete(User $user, Service $service): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }
}
