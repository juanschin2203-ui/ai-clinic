<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class AccountLockedException extends RuntimeException
{
    public function __construct(
        public readonly int $secondsUntilUnlock,
        string $message = 'Account is temporarily locked due to repeated failed login attempts.',
    ) {
        parent::__construct($message);
    }
}
