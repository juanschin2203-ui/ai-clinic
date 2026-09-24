<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice — monthly billing artifact. NOT soft-deletable (financial record).
 * If an invoice needs to be voided, record it via a zero-amount credit
 * payment row rather than a delete.
 */
class Invoice extends AbstractRocketModel
{
    use BelongsToTenant;

    protected $fillable = [
        'number',
        'clinicId',
        'period',
        'status',
        'subtotal',
        'tax',
        'total',
        'claimCount',
        'aiCount',
        'fullServiceCount',
        'invoiceDate',
        'dueDate',
        'sentAt',
        'paidAt',
        'billingMethod',
        'pdfFileId',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'claimCount' => 'integer',
        'aiCount' => 'integer',
        'fullServiceCount' => 'integer',
        'invoiceDate' => 'date',
        'dueDate' => 'date',
        'sentAt' => 'datetime',
        'paidAt' => 'datetime',
        'billingMethod' => BillingMethod::class,
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoiceId');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoiceId');
    }

    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'pdfFileId');
    }
}
