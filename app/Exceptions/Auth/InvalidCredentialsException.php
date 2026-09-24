<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Invalid credentials.')
    {
        parent::__construct($message);
    }
}
