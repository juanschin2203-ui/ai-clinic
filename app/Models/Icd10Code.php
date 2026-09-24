<?php

declare(strict_types=1);

namespace App\Models;

class Icd10Code extends AbstractRocketModel
{
    protected $table = 'icd10_codes';

    protected $fillable = [
        'code',
        'description',
        'chapter',
        'effectiveFrom',
        'effectiveTo',
        'billable',
        'active',
    ];

    protected $casts = [
        'effectiveFrom' => 'date',
        'effectiveTo' => 'date',
        'billable' => 'boolean',
        'active' => 'boolean',
    ];
}
