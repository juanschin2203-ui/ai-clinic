<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmrStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmrConnection extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'emr_connections';

    protected $fillable = [
        'clinicId',
        'emrSystemId',
        'status',
        'endpoint',
        'credentialsRef',
        'lastSync',
        'lastTestOk',
        'lastError',
        'syncFields',
    ];

    protected $casts = [
        'status' => EmrStatus::class,
        'lastSync' => 'datetime',
        'lastTestOk' => 'datetime',
        'syncFields' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function emrSystem(): BelongsTo
    {
        return $this->belongsTo(EmrSystem::class, 'emrSystemId');
    }
}
