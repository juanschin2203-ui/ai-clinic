<?php

declare(strict_types=1);

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;

class UserLoggedIn
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly ?string $clinicId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
    ) {}
}
