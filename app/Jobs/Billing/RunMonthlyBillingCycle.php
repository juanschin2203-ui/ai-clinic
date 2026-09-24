<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\ActivityLog;
use App\Models\Clinic;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Run on the 1st of each month via the Laravel scheduler. Fans out one
 * `GenerateClinicInvoice` job per active clinic, billing the PRIOR month's
 * completed cases.
 *
 * The entry-point job does the fan-out only — no invoice work here. That
 * way a clinic-count-of-N run has N+1 jobs in the queue, each clinic's
 * work runs independently, and the cycle as a whole completes even if
 * one clinic errors.
 */
class RunMonthlyBillingCycle implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        /** Optional override for the period to bill; null → prior calendar month. */
        public readonly ?string $period = null,
    ) {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        $period = $this->period ?? CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');

        $activeClinics = Clinic::withoutGlobalScopes()
            ->where('active', true)
            ->get();

        ActivityLog::query()->create([
            'category' => 'billing',
            'event' => 'billing.cycle.started',
            'severity' => 'info',
            'message' => "Billing cycle for {$period} — {$activeClinics->count()} active clinics.",
            'context' => ['period' => $period, 'clinicCount' => $activeClinics->count()],
            'occurredAt' => CarbonImmutable::now(),
        ]);

        foreach ($activeClinics as $clinic) {
            GenerateClinicInvoice::dispatch($clinic->id, $period);
        }
    }
}
