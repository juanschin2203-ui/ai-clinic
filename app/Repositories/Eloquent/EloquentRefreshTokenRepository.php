<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use Carbon\CarbonImmutable;

class EloquentRefreshTokenRepository extends BaseRepository implements RefreshTokenRepositoryInterface
{
    protected function model(): string
    {
        return RefreshToken::class;
    }

    public function findByHash(string $hash): ?RefreshToken
    {
        /** @var RefreshToken|null */
        return $this->query()->where('tokenHash', $hash)->first();
    }

    public function issue(
        User $user,
        string $hash,
        int $ttlMinutes,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): RefreshToken {
        $now = CarbonImmutable::now();

        /** @var RefreshToken */
        return RefreshToken::query()->create([
            'userId' => $user->id,
            'tokenHash' => $hash,
            'issuedAt' => $now,
            'expiresAt' => $now->addMinutes($ttlMinutes),
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ]);
    }

    public function markUsed(RefreshToken $token, string $replacedByTokenId): RefreshToken
    {
        $token->forceFill([
            'usedAt' => CarbonImmutable::now(),
            'replacedByTokenId' => $replacedByTokenId,
        ])->save();

        return $token->refresh();
    }

    public function revoke(RefreshToken $token): RefreshToken
    {
        $token->forceFill([
            'revokedAt' => CarbonImmutable::now(),
        ])->save();

        return $token->refresh();
    }

    public function revokeAllForUser(User $user): int
    {
        $now = CarbonImmutable::now();

        return $this->query()
            ->where('userId', $user->id)
            ->whereNull('usedAt')
            ->whereNull('revokedAt')
            ->update(['revokedAt' => $now, 'updatedAt' => $now]);
    }
}
