<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmrType;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmrSystem extends AbstractRocketModel
{
    protected $table = 'emr_systems';

    protected $fillable = [
        'name',
        'type',
        'typeLabel',
        'icon',
        'fields',
        'active',
    ];

    protected $casts = [
        'type' => EmrType::class,
        'fields' => 'array',
        'active' => 'boolean',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(EmrConnection::class, 'emrSystemId');
    }
}
