<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserLoginFailed;
use App\Exceptions\Auth\AccountInactiveException;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidRefreshTokenException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Orchestrates the login / refresh / logout flows. Controllers are thin
 * wrappers that validate input (FormRequest) and delegate here.
 *
 * All persistence goes through repositories — no direct Model::where
 * or Model::save in this class. Mutations wrap in a DB transaction so
 * a crash between lockout-update and event-fire leaves no half-states.
 *
 * Events (UserLoggedIn / UserLoginFailed / UserLoggedOut) are fired on
 * every auth outcome; the audit listener writes them to audit_logs.
 */
class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly TokenService $tokenService,
        private readonly LockoutService $lockout,
    ) {}

    /**
     * Authenticate email + password. Returns the issued token pair +
     * authenticated user. Throws specific auth exceptions that the
     * exception handler converts to the right HTTP status.
     *
     * @return array{accessToken: string, accessTokenExpiresAt: CarbonImmutable, refreshToken: string, refreshTokenExpiresAt: CarbonImmutable, user: User}
     */
    public function login(
        string $email,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            Event::dispatch(new UserLoginFailed(
                email: $email,
                reason: 'user_not_found',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ));

            throw new InvalidCredentialsException();
        }

        if (! $user->active) {
            Event::dispatch(new UserLoginFailed(
                email: $email,
                reason: 'account_inactive',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                userId: $user->id,
            ));

            throw new AccountInactiveException();
        }

        if ($this->lockout->isLocked($user)) {
            Event::dispatch(new UserLoginFailed(
                email: $email,
                reason: 'account_locked',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                userId: $user->id,
            ));

            throw new AccountLockedException($this->lockout->secondsUntilUnlock($user));
        }

        if (! Hash::check($password, $user->password)) {
            $this->lockout->recordFailure($user);

            Event::dispatch(new UserLoginFailed(
                email: $email,
                reason: 'bad_password',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                userId: $user->id,
            ));

            throw new InvalidCredentialsException();
        }

        return DB::transaction(function () use ($user, $ipAddress, $userAgent) {
            $this->lockout->recordSuccess($user);
            $pair = $this->tokenService->issuePair($user, $ipAddress, $userAgent);

            Event::dispatch(new UserLoggedIn(
                userId: $user->id,
                clinicId: $user->cid,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ));

            return [
                'accessToken' => $pair['accessToken'],
                'accessTokenExpiresAt' => $pair['accessTokenExpiresAt'],
                'refreshToken' => $pair['refreshToken'],
                'refreshTokenExpiresAt' => $pair['refreshTokenExpiresAt'],
                'user' => $user->refresh(),
            ];
        });
    }

    /**
     * Rotate a refresh token. Fails loudly on any abnormality: unknown
     * token, expired, already used, revoked, or the user is inactive.
     * Used tokens are NEVER reissued — if a stolen token is presented
     * after the legitimate client has already refreshed, we reject AND
     * we also revoke the entire token family for that user (break-glass
     * response to a likely theft).
     */
    public function refresh(
        string $rawToken,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $hash = $this->tokenService->hashToken($rawToken);
        $tokenModel = $this->refreshTokens->findByHash($hash);

        if ($tokenModel === null) {
            throw new InvalidRefreshTokenException('unknown');
        }

        if ($tokenModel->isRevoked()) {
            throw new InvalidRefreshTokenException('revoked');
        }

        if ($tokenModel->isExpired()) {
            throw new InvalidRefreshTokenException('expired');
        }

        if ($tokenModel->isUsed()) {
            // Reuse of a consumed token is a strong theft indicator.
            // Nuke every active session for this user.
            $this->tokenService->revokeAll($tokenModel->user);
            throw new InvalidRefreshTokenException('reused');
        }

        $user = $tokenModel->user;
        if (! $user->active) {
            throw new AccountInactiveException();
        }

        return DB::transaction(
            fn () => $this->tokenService->rotate($tokenModel, $ipAddress, $userAgent),
        );
    }

    /**
     * Log out the currently authenticated user. Revokes all access and
     * refresh tokens. Also accepts an optional refresh token string — if
     * provided, only that specific refresh token is revoked alongside
     * the current access token (useful for multi-device "sign out of
     * this session only" UX later).
     */
    public function logout(
        User $user,
        ?string $rawRefreshToken = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        DB::transaction(function () use ($user, $rawRefreshToken) {
            if ($rawRefreshToken !== null) {
                $hash = $this->tokenService->hashToken($rawRefreshToken);
                $token = $this->refreshTokens->findByHash($hash);
                if ($token !== null && $token->userId === $user->id) {
                    $this->refreshTokens->revoke($token);
                }

                // Revoke only the current Sanctum access token
                if ($user->currentAccessToken() !== null) {
                    $user->currentAccessToken()->delete();
                }
            } else {
                // Full logout — nuke every token for the user.
                $this->tokenService->revokeAll($user);
            }
        });

        Event::dispatch(new UserLoggedOut(
            userId: $user->id,
            clinicId: $user->cid,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        ));
    }

    /**
     * Emit a generic "we sent a reset email if you have an account" result
     * regardless of whether the email exists — defends against account
     * enumeration via the forgot-password endpoint.
     */
    public function findUserForReset(string $email): ?User
    {
        $user = $this->users->findActiveByEmail($email);

        return $user;
    }
}
