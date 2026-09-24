<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findActiveByEmail(string $email): ?User;

    public function recordLoginSuccess(User $user): User;

    public function recordLoginFailure(User $user): User;

    public function clearLockout(User $user): User;

    public function applyLockout(User $user, int $minutes): User;
}
