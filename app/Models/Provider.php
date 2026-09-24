<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    /** Tenant FK name matches JSX (`clinic`, not `clinicId`). */
    protected static string $tenantColumn = 'clinic';

    protected $fillable = [
        'clinic',
        'userId',
        'name',
        'npi',
        'license',
        'specialty',
        'sigBlock',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'providerId');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'providerId');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class, 'providerId');
    }
}
