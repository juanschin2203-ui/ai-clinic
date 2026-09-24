<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\User;

/**
 * Clinics are admin-managed. Clinic users can see and update their OWN
 * clinic record (for the Config screen) but cannot create or delete
 * clinics — those are Rocket admin / deployer operations.
 */
class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        // Only Rocket admins + deployers see the list of clinics.
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }

    public function view(User $user, Clinic $clinic): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid === $clinic->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }

    public function update(User $user, Clinic $clinic): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        // Clinic user with `full` permission can edit their own clinic's settings.
        return $user->cid === $clinic->id
            && $user->permission?->value === 'full';
    }

    public function delete(User $user, Clinic $clinic): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true);
    }
}
