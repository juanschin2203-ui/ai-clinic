<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        // Admin/deployer see everyone. Clinic `full` users see their clinic's users.
        if (in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $actor->role === UserRole::Clinic
            && $actor->permission?->value === 'full';
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;  // can always see self
        }

        if (in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $actor->cid !== null
            && $actor->cid === $target->cid
            && $actor->permission?->value === 'full';
    }

    public function create(User $actor): bool
    {
        if (in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $actor->role === UserRole::Clinic && $actor->permission?->value === 'full';
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;  // self-service (name, email) — field-level guard in FormRequest
        }

        if (in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $actor->cid !== null
            && $actor->cid === $target->cid
            && $actor->permission?->value === 'full';
    }

    public function delete(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;  // can't delete self
        }

        if (in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $actor->cid !== null
            && $actor->cid === $target->cid
            && $actor->permission?->value === 'full';
    }
}
