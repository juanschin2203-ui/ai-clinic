<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\RefreshToken;
use App\Models\User;

interface RefreshTokenRepositoryInterface extends RepositoryInterface
{
    /** Look up by the SHA-256 hex digest of the raw token. */
    public function findByHash(string $hash): ?RefreshToken;

    public function issue(
        User $user,
        string $hash,
        int $ttlMinutes,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): RefreshToken;

    public function markUsed(RefreshToken $token, string $replacedByTokenId): RefreshToken;

    public function revoke(RefreshToken $token): RefreshToken;

    /** Revoke every active (not yet used, not yet revoked) refresh token for a user. */
    public function revokeAllForUser(User $user): int;
}
