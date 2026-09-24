<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Implements the lockout state machine per the user manual:
 *
 *   - Users get `lockout_attempts` (default 3) failed logins before locking.
 *   - On the Nth fail, `lockedUntil` = now + `lockout_minutes` (default 15).
 *   - While locked, login requests are rejected with 423 Locked without
 *     password verification (avoids timing leak of whether the password was
 *     right — doesn't matter, they're locked either way).
 *   - Successful login clears both loginAttempts and lockedUntil.
 *
 * State lives on the users table (`loginAttempts`, `lockedUntil` columns).
 */
class LockoutService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Is this user currently locked out? True if `lockedUntil` is set and
     * still in the future.
     */
    public function isLocked(User $user): bool
    {
        if ($user->lockedUntil === null) {
            return false;
        }

        return CarbonImmutable::parse($user->lockedUntil)->isFuture();
    }

    /**
     * Seconds remaining in the current lockout window. Zero if not locked.
     */
    public function secondsUntilUnlock(User $user): int
    {
        if (! $this->isLocked($user)) {
            return 0;
        }

        return max(0, CarbonImmutable::parse($user->lockedUntil)->diffInSeconds(CarbonImmutable::now()));
    }

    /**
     * Record a failed login attempt. Applies a lockout when the threshold
     * is crossed. Returns the (refreshed) user.
     */
    public function recordFailure(User $user): User
    {
        $user = $this->users->recordLoginFailure($user);

        $threshold = (int) config('rocket_auth.lockout_attempts');
        if ($user->loginAttempts >= $threshold) {
            $user = $this->users->applyLockout(
                $user,
                (int) config('rocket_auth.lockout_minutes'),
            );
        }

        return $user;
    }

    /**
     * Clear lockout state on successful login.
     */
    public function recordSuccess(User $user): User
    {
        return $this->users->recordLoginSuccess($user);
    }
}
