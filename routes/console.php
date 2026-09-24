<?php

declare(strict_types=1);

use App\Jobs\Billing\InitiateAchDebit;
use App\Jobs\Billing\RunMonthlyBillingCycle;
use App\Jobs\CleanupOrphanedFiles;
use App\Models\Invoice;
use Illuminate\Support\Facades\Schedule;

// ============================================================================
// Scheduled jobs — run by `php artisan schedule:run` (set as a 1-minute cron
// on the production host, or run inside the Horizon supervisor in Step 7).
// ============================================================================

// Monthly billing — 1st of each month at 03:00 UTC. `onOneServer()` guards
// against multi-worker deployments double-dispatching. Laravel requires
// `name()` to be called BEFORE `onOneServer()` (the name is the lock key).
Schedule::job(new RunMonthlyBillingCycle())
    ->monthlyOn(1, '03:00')
    ->name('rc.billing.monthly')
    ->onOneServer()
    ->description('Generate invoices + send emails for all active clinics.');

// ACH debits — 10th of each month at 09:00 UTC. Fans out one job per
// unpaid `sent` invoice whose dueDate ≤ today.
Schedule::call(function (): void {
    Invoice::withoutGlobalScopes()
        ->where('status', 'sent')
        ->where('dueDate', '<=', now()->toDateString())
        ->get()
        ->each(fn ($inv) => InitiateAchDebit::dispatch($inv->id));
})->monthlyOn(10, '09:00')
    ->name('rc.billing.ach')
    ->onOneServer()
    ->description('Initiate ACH debits for unpaid invoices due today or earlier.');

// Orphaned-file sweep — daily at 02:00 UTC. Frees storage for rows whose
// owner was hard-deleted.
Schedule::job(new CleanupOrphanedFiles())
    ->daily()
    ->at('02:00')
    ->name('rc.files.cleanup')
    ->onOneServer()
    ->description('Purge storage + metadata for orphaned file rows.');
