<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\File;
use App\Models\User;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;
    }

    public function view(User $user, File $file): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->cid === $file->clinicId;
    }

    public function create(User $user): bool
    {
        // Admins, clinic users with write permission, providers, schedulers can upload.
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->permission?->canWrite() === true;
    }

    public function delete(User $user, File $file): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        // `full` permission only — deleting PHI is sensitive.
        return $user->cid !== null
            && $user->cid === $file->clinicId
            && $user->permission?->value === 'full';
    }
}
