<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Clinic;
use App\Services\Billing\InvoiceGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the invoice for one clinic + period. Dispatched by
 * RunMonthlyBillingCycle (one job per active clinic) so each runs
 * independently — one clinic's billing failure doesn't block the rest.
 */
class GenerateClinicInvoice implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        public readonly string $clinicId,
        public readonly string $period,
    ) {
        $this->onQueue('billing');
    }

    public function handle(InvoiceGenerationService $invoices): void
    {
        $clinic = Clinic::withoutGlobalScopes()->find($this->clinicId);
        if ($clinic === null || ! $clinic->active) {
            return;
        }

        $invoice = $invoices->generateForClinicAndPeriod($clinic, $this->period);

        // Once the invoice has line items, email it. Only emails drafts on
        // first generation — status transitions to 'sent' inside SendInvoiceEmail.
        if ($invoice->status === 'draft') {
            SendInvoiceEmail::dispatch($invoice->id)->onQueue('billing');
        }
    }
}
