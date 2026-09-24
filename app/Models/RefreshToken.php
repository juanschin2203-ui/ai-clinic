<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RefreshToken — rotating long-lived token for minting new Sanctum access
 * tokens. The raw token is never stored; only its SHA-256 hash.
 *
 * Step 4 implements the login + refresh flow:
 *   - /auth/login   → issues {accessToken (15 min), refreshToken (7 days)}
 *   - /auth/refresh → validates refresh token, marks it used, issues new pair
 *   - /auth/logout  → revokes the current refresh token
 */
class RefreshToken extends AbstractRocketModel
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'userId',
        'tokenHash',
        'issuedAt',
        'expiresAt',
        'usedAt',
        'revokedAt',
        'replacedByTokenId',
        'ipAddress',
        'userAgent',
    ];

    protected $hidden = [
        'tokenHash',   // never leak even hashed tokens in API responses
    ];

    protected $casts = [
        'issuedAt' => 'datetime',
        'expiresAt' => 'datetime',
        'usedAt' => 'datetime',
        'revokedAt' => 'datetime',
    ];

    // ----- domain methods ---------------------------------------------------

    public function isActive(): bool
    {
        return $this->usedAt === null
            && $this->revokedAt === null
            && CarbonImmutable::parse($this->expiresAt)->isFuture();
    }

    public function isExpired(): bool
    {
        return CarbonImmutable::parse($this->expiresAt)->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    // ----- relationships ----------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(RefreshToken::class, 'replacedByTokenId');
    }
}
