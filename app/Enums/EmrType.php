<?php

declare(strict_types=1);

namespace App\Enums;

enum EmrType: string
{
    case Fhir = 'fhir';
    case Rest = 'rest';
    case Sftp = 'sftp';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Fhir => 'FHIR (HL7)',
            self::Rest => 'REST API',
            self::Sftp => 'SFTP Batch',
            self::Api => 'Generic API',
        };
    }
}
