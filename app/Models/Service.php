<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Service — a billable item the clinic orders from Rocket Coding.
 *
 * PK is a short string ("s1", "s16", "sT") preserved from JSX, NOT a UUID.
 * This lets the `cases.svcs` JSON array reference services by the same keys
 * the prototype uses without any remapping during seed.
 */
class Service extends Model
{
    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cat',
        'title',
        'price',
        'active',
        'sortOrder',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
        'sortOrder' => 'integer',
    ];
}
