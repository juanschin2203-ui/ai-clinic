<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class InvalidRefreshTokenException extends RuntimeException
{
    /**
     * $reason values: "unknown" | "revoked" | "expired" | "reused".
     * Logged (and audited) but NOT returned to the client to avoid
     * leaking which condition failed — the error response is always
     * a generic 401.
     */
    public function __construct(
        public readonly string $reason,
        string $message = 'Invalid refresh token.',
    ) {
        parent::__construct($message);
    }
}
