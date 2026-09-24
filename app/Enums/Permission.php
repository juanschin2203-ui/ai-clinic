<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case Full = 'full';
    case Coder = 'coder';
    case Scheduler = 'scheduler';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Access',
            self::Coder => 'Coder',
            self::Scheduler => 'Scheduler',
            self::Viewer => 'View-Only',
        };
    }

    public function canWrite(): bool
    {
        return in_array($this, [self::Full, self::Coder, self::Scheduler], strict: true);
    }

    public function canCode(): bool
    {
        return in_array($this, [self::Full, self::Coder], strict: true);
    }
}
