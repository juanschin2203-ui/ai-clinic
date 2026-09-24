<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsState;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clinic — the tenant root.
 *
 * selfCoded=true means the clinic codes its own cases (Rocket just submits
 * claims); false means Rocket codes end-to-end. This flag drives:
 *   - the Admin Coder screen's "We Code / Self-Coded" toggle
 *   - whether AI suggestions are surfaced to clinic users or stay internal
 *   - billing tier visibility
 */
class Clinic extends AbstractRocketModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'state',
        'email',
        'active',
        'city',
        'addr',
        'zip',
        'phone',
        'npi',
        'taxId',
        'timezone',
        'logo',
        'selfCoded',
        'reportFavs',
        'notif',
    ];

    protected $casts = [
        'active' => 'boolean',
        'selfCoded' => 'boolean',
        'reportFavs' => 'array',
        'notif' => 'array',
        'state' => UsState::class,
    ];

    // ----- relationships ----------------------------------------------------

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'cid');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'clinic');
    }

    public function clinicAdmins(): HasMany
    {
        return $this->hasMany(ClinicAdmin::class, 'clinic');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'clinicId');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class, 'clinic');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'clinicId');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'clinicId');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'clinicId');
    }

    public function emrConnections(): HasMany
    {
        return $this->hasMany(EmrConnection::class, 'clinicId');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'clinicId');
    }
}
