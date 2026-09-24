<?php

declare(strict_types=1);

namespace App\Enums;

enum EmrStatus: string
{
    case Connected = 'connected';
    case Pending = 'pending';
    case NotConnected = 'notConnected';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Pending => 'Pending',
            self::NotConnected => 'Not Connected',
        };
    }
}
