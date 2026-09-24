<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * STUB IMPLEMENTATION — production wires a real Stripe ACH / Plaid Transfer
 * API call here. Today's behavior:
 *
 *   - Reads the invoice's total and the clinic's default PaymentMethod
 *   - Creates a Payment row in status='initiated' with a fake provider ref
 *   - Writes an ActivityLog 'billing.ach.initiated' event
 *   - Does NOT charge any real account
 *
 * Real ACH integration lands when Snap Solutions has a Stripe / Plaid
 * account wired. At that point, replace the `initiateProviderDebit()`
 * method body and the webhook handler (not yet scaffolded) will flip
 * Payment.status to 'succeeded' or 'failed' asynchronously.
 */
class InitiateAchDebit implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly string $invoiceId,
    ) {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        $invoice = Invoice::withoutGlobalScopes()->find($this->invoiceId);
        if ($invoice === null || $invoice->status !== 'sent') {
            return;
        }

        $providerRef = $this->initiateProviderDebit();

        Payment::query()->create([
            'invoiceId' => $invoice->id,
            'clinicId' => $invoice->clinicId,
            'method' => 'ach',
            'amount' => $invoice->total,
            'providerRef' => $providerRef,
            'status' => 'initiated',
            'initiatedAt' => CarbonImmutable::now(),
        ]);

        $invoice->forceFill(['status' => 'paying'])->save();

        ActivityLog::query()->create([
            'clinicId' => $invoice->clinicId,
            'category' => 'billing',
            'event' => 'billing.ach.initiated',
            'severity' => 'info',
            'message' => "ACH debit initiated for invoice {$invoice->number} amount \${$invoice->total}.",
            'context' => ['invoiceId' => $invoice->id, 'providerRef' => $providerRef],
            'occurredAt' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Placeholder — real impl calls Stripe::paymentIntents()->create() or
     * the Plaid transfer API and returns the provider's transaction id.
     */
    private function initiateProviderDebit(): string
    {
        return 'stub-'.Str::uuid();
    }
}
