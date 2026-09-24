<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password-reset token issue + consume. Tokens are stored in Laravel's
 * stock `password_reset_tokens` table, hashed. Raw token is returned once
 * (by email) and never persisted.
 */
class PasswordResetService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TokenService $tokens,
    ) {}

    /**
     * Issue a reset token for the user. Returns the raw token (to be
     * emailed). Any previous unused token for this email is overwritten.
     */
    public function issue(User $user): string
    {
        $raw = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($raw),
                'createdAt' => CarbonImmutable::now(),
            ],
        );

        return $raw;
    }

    /**
     * Consume the token. Returns the user on success, null on failure
     * (bad token / expired / unknown email). On success, clears the token
     * row and revokes all existing sessions for the user.
     */
    public function consume(string $email, string $rawToken, string $newPassword): ?User
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($row === null) {
            return null;
        }

        $ttl = (int) config('rocket_auth.password_reset_token_ttl_minutes');
        if (CarbonImmutable::parse($row->createdAt)->addMinutes($ttl)->isPast()) {
            return null;
        }

        if (! Hash::check($rawToken, $row->token)) {
            return null;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $user->forceFill([
            'password' => $newPassword,      // Model casts to 'hashed' — Laravel handles bcrypt.
            'loginAttempts' => 0,
            'lockedUntil' => null,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $this->tokens->revokeAll($user);

        return $user->refresh();
    }
}
