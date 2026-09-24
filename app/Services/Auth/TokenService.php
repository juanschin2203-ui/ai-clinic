<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Issues and rotates the access+refresh token pair.
 *
 * Access tokens are Sanctum's opaque, DB-backed tokens — short-lived
 * (15 min by default), bearer-style, validated by Sanctum on every request.
 * Refresh tokens are a separate random 64-byte string; only the SHA-256
 * hash is stored server-side, never the raw token.
 */
class TokenService
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    /**
     * Issue a fresh access+refresh pair for a user.
     *
     * @return array{accessToken: string, accessTokenExpiresAt: CarbonImmutable, refreshToken: string, refreshTokenExpiresAt: CarbonImmutable, refreshTokenModel: RefreshToken}
     */
    public function issuePair(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $accessTtl = (int) config('rocket_auth.access_token_ttl_minutes');
        $refreshTtl = (int) config('rocket_auth.refresh_token_ttl_minutes');

        $accessToken = $user->createToken(
            name: 'access',
            abilities: config('rocket_auth.access_token_abilities'),
            expiresAt: CarbonImmutable::now()->addMinutes($accessTtl),
        );

        $rawRefresh = $this->generateRawToken();
        $refreshModel = $this->refreshTokens->issue(
            user: $user,
            hash: $this->hashToken($rawRefresh),
            ttlMinutes: $refreshTtl,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return [
            'accessToken' => $accessToken->plainTextToken,
            'accessTokenExpiresAt' => CarbonImmutable::parse($accessToken->accessToken->expires_at),
            'refreshToken' => $rawRefresh,
            'refreshTokenExpiresAt' => CarbonImmutable::parse($refreshModel->expiresAt),
            'refreshTokenModel' => $refreshModel,
        ];
    }

    /**
     * Rotate a valid refresh token into a new pair. The old token is marked
     * `usedAt` and linked to the new one via `replacedByTokenId`.
     *
     * @return array{accessToken: string, accessTokenExpiresAt: CarbonImmutable, refreshToken: string, refreshTokenExpiresAt: CarbonImmutable, user: User}
     */
    public function rotate(
        RefreshToken $oldToken,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $user = $oldToken->user;

        $newPair = $this->issuePair($user, $ipAddress, $userAgent);
        $this->refreshTokens->markUsed($oldToken, $newPair['refreshTokenModel']->id);

        return [
            'accessToken' => $newPair['accessToken'],
            'accessTokenExpiresAt' => $newPair['accessTokenExpiresAt'],
            'refreshToken' => $newPair['refreshToken'],
            'refreshTokenExpiresAt' => $newPair['refreshTokenExpiresAt'],
            'user' => $user,
        ];
    }

    /**
     * Revoke all active Sanctum access tokens + all active refresh tokens
     * for the user. Used at logout and on forced session termination.
     */
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();           // Sanctum access tokens
        $this->refreshTokens->revokeAllForUser($user);
    }

    public function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    private function generateRawToken(): string
    {
        // 64 bytes -> 128 hex chars; more than enough entropy.
        return Str::random(64);
    }
}
