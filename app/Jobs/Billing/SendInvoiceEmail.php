<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Mail\InvoiceMail;
use App\Models\Clinic;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Mails the invoice to the clinic's billing contact and transitions the
 * invoice from draft → sent. Idempotent: if the invoice is already `sent`
 * or beyond, the job no-ops (avoids re-emailing after manual re-runs).
 */
class SendInvoiceEmail implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 120;

    public function __construct(
        public readonly string $invoiceId,
    ) {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::withoutGlobalScopes()->find($this->invoiceId);
        if ($invoice === null || $invoice->status !== 'draft') {
            return;
        }

        $clinic = Clinic::withoutGlobalScopes()->find($invoice->clinicId);
        if ($clinic === null || ! $clinic->email) {
            return;
        }

        Mail::to($clinic->email)
            ->send(new InvoiceMail(
                invoice: $invoice,
                clinic: $clinic,
            ));

        $invoice->forceFill([
            'status' => 'sent',
            'sentAt' => CarbonImmutable::now(),
        ])->save();
    }
}
