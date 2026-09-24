<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PaymentMethod — a clinic's saved ACH account or card.
 * Stores a provider-side token (`providerRef`) plus display-safe metadata
 * only. Real bank/card numbers never touch our DB.
 */
class PaymentMethod extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'clinicId',
        'method',
        'provider',
        'providerRef',
        'brand',
        'last4',
        'holderName',
        'isDefault',
        'active',
        'verifiedAt',
    ];

    protected $hidden = [
        'providerRef',       // token — not leaked in API responses by default
    ];

    protected $casts = [
        'method' => BillingMethod::class,
        'isDefault' => 'boolean',
        'active' => 'boolean',
        'verifiedAt' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'paymentMethodId');
    }
}
