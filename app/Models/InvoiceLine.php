<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends AbstractRocketModel
{
    protected $table = 'invoice_lines';

    protected $fillable = [
        'invoiceId',
        'caseId',
        'tier',
        'description',
        'quantity',
        'unitPrice',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unitPrice' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoiceId');
    }

    public function case_(): BelongsTo
    {
        return $this->belongsTo(MedicalCase::class, 'caseId');
    }
}
