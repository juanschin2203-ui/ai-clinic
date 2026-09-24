<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'clinicId',
        'patientId',
        'providerId',
        'date',
        'time',
        'patient',
        'provider',
        'company',
        'visitType',
        'claim',
        'dictation',
        'chartUploaded',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'visitType' => ReportType::class,
        'dictation' => 'boolean',
        'chartUploaded' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function patientModel(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function providerModel(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'providerId');
    }
}
