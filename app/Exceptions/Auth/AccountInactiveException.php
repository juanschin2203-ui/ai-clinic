<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class AccountInactiveException extends RuntimeException
{
    public function __construct(string $message = 'Account is inactive.')
    {
        parent::__construct($message);
    }
}
