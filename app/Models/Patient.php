<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient — PHI.
 *
 * Every read and write of this model emits an audit entry via the
 * PHI observer registered in AuditServiceProvider (Step 4). Tenant scope
 * is enforced by the BelongsToTenant trait — a clinic user cannot see
 * another clinic's patients, period.
 */
class Patient extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'clinicId',
        'providerId',
        'name',
        'dob',
        'gender',
        'emrId',
        'phone',
        'email',
        'employer',
        'doi',
        'claimNum',
        'reportStatus',
    ];

    protected $casts = [
        'dob' => 'date',
        'doi' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'providerId');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patientId');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class, 'patientId');
    }
}
