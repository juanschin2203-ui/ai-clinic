<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CaseStatus;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MedicalCase;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Generates a monthly invoice for a single clinic. Idempotent on
 * (clinicId, period) — re-running is safe and returns the existing invoice.
 *
 * Inputs:
 *   - clinic (model)
 *   - period: "YYYY-MM" string identifying the billing month (the month
 *            whose cases are being billed — invoice is sent/due in the
 *            FOLLOWING month on the 1st/10th)
 *
 * Algorithm (matches the JSX prototype's additive tier math):
 *   For each case in the clinic that reached Completed or Delivered in
 *   the period:
 *     - 1× Claim Submission line           @ $1.75
 *     - 1× AI Auto-Coding line if the case has non-empty suggestedCPT
 *          (i.e., AI actually produced output)  @ $1.00
 *     - 1× Full-Service Billing line if the clinic is not selfCoded
 *          (Rocket coded end-to-end for them)   @ $5.00
 *
 * Invoice number format: "RC-YYYY-MM-{first-7-of-clinic-uuid-upper}"
 * Due date: 10th of the month FOLLOWING the `period` month.
 */
class InvoiceGenerationService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    /**
     * Create-or-update the invoice for a clinic's given period.
     * Returns the Invoice model with its lines freshly written.
     */
    public function generateForClinicAndPeriod(Clinic $clinic, string $period): Invoice
    {
        return DB::transaction(function () use ($clinic, $period) {
            [$start, $end] = $this->periodBounds($period);
            [$invoiceDate, $dueDate] = $this->invoiceDates($period);

            // Completed/Delivered cases in the period.
            $cases = MedicalCase::withoutGlobalScopes()
                ->where('clinic', $clinic->id)
                ->whereIn('status', [CaseStatus::Completed->value, CaseStatus::Delivered->value])
                ->whereBetween('updatedAt', [$start, $end])
                ->get();

            // Compute line items per-case — additive.
            $lines = [];
            $claimCount = 0;
            $aiCount = 0;
            $fullServiceCount = 0;

            foreach ($cases as $case) {
                $claimCount++;
                $lines[] = [
                    'caseId' => $case->id,
                    'tier' => TierPricing::TIER_CLAIM_SUBMISSION,
                    'description' => "Claim Submission — {$case->num}",
                    'quantity' => 1,
                    'unitPrice' => TierPricing::CLAIM_SUBMISSION_USD,
                    'amount' => TierPricing::CLAIM_SUBMISSION_USD,
                ];

                if (! empty($case->suggestedCPT)) {
                    $aiCount++;
                    $lines[] = [
                        'caseId' => $case->id,
                        'tier' => TierPricing::TIER_AI_AUTO_CODING,
                        'description' => "AI Auto-Coding — {$case->num}",
                        'quantity' => 1,
                        'unitPrice' => TierPricing::AI_AUTO_CODING_USD,
                        'amount' => TierPricing::AI_AUTO_CODING_USD,
                    ];
                }

                if (! $clinic->selfCoded) {
                    $fullServiceCount++;
                    $lines[] = [
                        'caseId' => $case->id,
                        'tier' => TierPricing::TIER_FULL_SERVICE,
                        'description' => "Full-Service Billing — {$case->num}",
                        'quantity' => 1,
                        'unitPrice' => TierPricing::FULL_SERVICE_BILLING_USD,
                        'amount' => TierPricing::FULL_SERVICE_BILLING_USD,
                    ];
                }
            }

            $subtotal = array_sum(array_column($lines, 'amount'));
            $tax = 0.0;
            $total = $subtotal + $tax;

            // Upsert the Invoice row.
            $invoice = $this->invoices->findForPeriod($clinic->id, $period);

            $payload = [
                'clinicId' => $clinic->id,
                'period' => $period,
                'status' => $invoice?->status ?? 'draft',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'claimCount' => $claimCount,
                'aiCount' => $aiCount,
                'fullServiceCount' => $fullServiceCount,
                'invoiceDate' => $invoiceDate,
                'dueDate' => $dueDate,
                'billingMethod' => 'ach',
                'number' => $this->invoiceNumber($clinic, $period),
            ];

            if ($invoice === null) {
                /** @var Invoice $invoice */
                $invoice = $this->invoices->create($payload);
            } else {
                // Only recompute financial fields on a draft. Once sent, the
                // invoice is immutable — a mid-period regeneration attempt
                // short-circuits and returns the existing row unchanged.
                if ($invoice->status === 'draft') {
                    /** @var Invoice $invoice */
                    $invoice = $this->invoices->update($invoice->id, $payload);
                } else {
                    return $invoice;
                }
            }

            // Replace lines (only safe because invoice is still draft).
            InvoiceLine::query()->where('invoiceId', $invoice->id)->delete();
            foreach ($lines as $line) {
                InvoiceLine::query()->create(['invoiceId' => $invoice->id] + $line);
            }

            return $invoice->refresh();
        });
    }

    private function invoiceNumber(Clinic $clinic, string $period): string
    {
        $shortId = strtoupper(substr(str_replace('-', '', $clinic->id), 0, 7));

        return "RC-{$period}-{$shortId}";
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodBounds(string $period): array
    {
        $start = CarbonImmutable::parse($period.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        return [$start, $end];
    }

    /** @return array{0: string, 1: string} */
    private function invoiceDates(string $period): array
    {
        // Invoice sent 1st of the month FOLLOWING the billing period;
        // due on the 10th (ACH debit day).
        $next = CarbonImmutable::parse($period.'-01')->addMonthNoOverflow();

        return [
            $next->startOfMonth()->toDateString(),
            $next->day(10)->toDateString(),
        ];
    }
}
