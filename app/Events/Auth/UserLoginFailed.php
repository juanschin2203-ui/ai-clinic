<?php

declare(strict_types=1);

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;

class UserLoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $reason,          // user_not_found | bad_password | account_inactive | account_locked
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $userId = null,  // present when we found the user but creds/state failed
    ) {}
}
