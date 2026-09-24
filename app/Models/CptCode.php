<?php

declare(strict_types=1);

namespace App\Models;

class CptCode extends AbstractRocketModel
{
    protected $table = 'cpt_codes';

    protected $fillable = [
        'code',
        'description',
        'category',
        'effectiveFrom',
        'effectiveTo',
        'rvuWork',
        'rvuPe',
        'rvuMp',
        'active',
    ];

    protected $casts = [
        'effectiveFrom' => 'date',
        'effectiveTo' => 'date',
        'rvuWork' => 'decimal:3',
        'rvuPe' => 'decimal:3',
        'rvuMp' => 'decimal:3',
        'active' => 'boolean',
    ];
}
