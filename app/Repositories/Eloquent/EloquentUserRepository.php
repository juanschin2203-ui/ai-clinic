<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

class EloquentUserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->query()->where('email', $email)->first();
    }

    public function findActiveByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->query()
            ->where('email', $email)
            ->where('active', true)
            ->first();
    }

    public function recordLoginSuccess(User $user): User
    {
        $user->forceFill([
            'lastLogin' => CarbonImmutable::now()->toDateString(),
            'loginAttempts' => 0,
            'lockedUntil' => null,
        ])->save();

        return $user->refresh();
    }

    public function recordLoginFailure(User $user): User
    {
        $user->forceFill([
            'loginAttempts' => $user->loginAttempts + 1,
        ])->save();

        return $user->refresh();
    }

    public function clearLockout(User $user): User
    {
        $user->forceFill([
            'loginAttempts' => 0,
            'lockedUntil' => null,
        ])->save();

        return $user->refresh();
    }

    public function applyLockout(User $user, int $minutes): User
    {
        $user->forceFill([
            'lockedUntil' => CarbonImmutable::now()->addMinutes($minutes),
        ])->save();

        return $user->refresh();
    }
}
