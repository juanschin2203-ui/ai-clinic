<?php

declare(strict_types=1);

namespace App\Models;

class HcpcsCode extends AbstractRocketModel
{
    protected $table = 'hcpcs_codes';

    protected $fillable = [
        'code',
        'description',
        'category',
        'effectiveFrom',
        'effectiveTo',
        'active',
    ];

    protected $casts = [
        'effectiveFrom' => 'date',
        'effectiveTo' => 'date',
        'active' => 'boolean',
    ];
}
