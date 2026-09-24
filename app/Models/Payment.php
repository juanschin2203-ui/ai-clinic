<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment — recorded state transition against an invoice. Immutable
 * (no updates, no deletes — every status change is a new row).
 */
class Payment extends AbstractRocketModel
{
    use BelongsToTenant;

    protected $fillable = [
        'invoiceId',
        'clinicId',
        'paymentMethodId',
        'status',
        'amount',
        'currency',
        'providerRef',
        'failureCode',
        'failureMessage',
        'initiatedAt',
        'settledAt',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiatedAt' => 'datetime',
        'settledAt' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoiceId');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'paymentMethodId');
    }
}
