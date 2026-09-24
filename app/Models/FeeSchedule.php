<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FeeSchedule — one row per US state (CA, TX, NY, FL, IL).
 * PK is the 2-letter state code (not a UUID).
 */
class FeeSchedule extends Model
{
    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $table = 'fee_schedules';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'sys',
        'm',
        'active',
    ];

    protected $casts = [
        'm' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class, 'feeScheduleCode', 'code');
    }
}
