<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingMethod: string
{
    case Ach = 'ach';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Ach => 'ACH Auto-Debit',
            self::Card => 'Credit Card',
        };
    }
}
