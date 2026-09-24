<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Clinic = 'clinic';
    case Provider = 'provider';
    case Staff = 'staff';
    case Admin = 'admin';
    case Deployer = 'deployer';

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'Clinic User',
            self::Provider => 'Provider',
            self::Staff => 'Staff / Scheduler',
            self::Admin => 'Rocket Coding Admin',
            self::Deployer => 'Deployer',
        };
    }

    public function requiresClinic(): bool
    {
        return in_array($this, [self::Clinic, self::Provider, self::Staff], strict: true);
    }
}
