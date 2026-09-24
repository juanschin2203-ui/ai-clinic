<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Locality extends AbstractRocketModel
{
    protected $fillable = [
        'feeScheduleCode',
        'code',
        'name',
        'adj',
        'active',
    ];

    protected $casts = [
        'adj' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class, 'feeScheduleCode', 'code');
    }
}
