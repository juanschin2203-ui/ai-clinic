<?php

declare(strict_types=1);

namespace App\Enums;

enum InputSource: string
{
    case Upload = 'upload';
    case Dictation = 'dictation';
    case Batch = 'batch';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Chart Upload',
            self::Dictation => 'Dictation',
            self::Batch => 'Batch Upload',
        };
    }
}
