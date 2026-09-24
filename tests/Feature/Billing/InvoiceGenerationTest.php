<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MedicalCase;
use App\Services\Billing\InvoiceGenerationService;
use App\Services\Billing\TierPricing;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->service = app(InvoiceGenerationService::class);
    $this->period = '2026-03';
});

/**
 * Helper: create a completed/delivered case, backdated into the target
 * billing period. `updatedAt` is guarded at the model level (timestamp
 * column), so we forceFill it after the insert.
 */
function makeBillableCase(string $clinicId, string $clinicName, ?array $suggestedCpt = null, string $status = 'completed'): MedicalCase
{
    /** @var MedicalCase $case */
    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-'.random_int(1000, 9999),
        'clinic' => $clinicId,
        'clinicName' => $clinicName,
        'patient' => 'Billed Patient',
        'state' => 'CA',
        'status' => $status,
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'suggestedCPT' => $suggestedCpt,
    ]);

    // Backdate updatedAt into the 2026-03 period. forceFill bypasses
    // the mass-assignment guard (which blocks writes to timestamp columns).
    $case->forceFill(['updatedAt' => CarbonImmutable::parse('2026-03-15')])->saveQuietly();

    return $case;
}

it('bills $1.75 per case for a self-coded clinic with no AI output', function () {
    $clinic = Clinic::factory()->selfCoded()->create(['name' => 'Valley Test']);
    makeBillableCase($clinic->id, $clinic->name);
    makeBillableCase($clinic->id, $clinic->name);

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($invoice->claimCount)->toBe(2);
    expect($invoice->aiCount)->toBe(0);
    expect($invoice->fullServiceCount)->toBe(0);
    expect((float) $invoice->subtotal)->toBe(3.50);       // 2 × $1.75
    expect((float) $invoice->total)->toBe(3.50);
});

it('adds $1.00 AI line for each case that has suggestedCPT output', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name, suggestedCpt: [
        ['code' => '99214', 'desc' => 'OV', 'citation' => 'x', 'confidence' => 0.9],
    ]);
    makeBillableCase($clinic->id, $clinic->name);       // no AI output

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($invoice->claimCount)->toBe(2);
    expect($invoice->aiCount)->toBe(1);
    expect((float) $invoice->total)->toBe(2 * 1.75 + 1 * 1.00);        // 4.50
});

it('adds $5.00 full-service line for every case at a we-code clinic', function () {
    $clinic = Clinic::factory()->create(['selfCoded' => false]);   // Rocket codes for them
    makeBillableCase($clinic->id, $clinic->name);
    makeBillableCase($clinic->id, $clinic->name);

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($invoice->claimCount)->toBe(2);
    expect($invoice->fullServiceCount)->toBe(2);
    expect((float) $invoice->total)->toBe(2 * (1.75 + 5.00));         // 13.50
});

it('additively bills claim + AI + full-service for the same case when all three apply', function () {
    $clinic = Clinic::factory()->create(['selfCoded' => false]);
    makeBillableCase($clinic->id, $clinic->name, suggestedCpt: [
        ['code' => '99215', 'desc' => 'x', 'citation' => 'y', 'confidence' => 0.85],
    ]);

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($invoice->claimCount)->toBe(1);
    expect($invoice->aiCount)->toBe(1);
    expect($invoice->fullServiceCount)->toBe(1);
    expect((float) $invoice->total)->toBe(1.75 + 1.00 + 5.00);        // 7.75

    $lines = InvoiceLine::where('invoiceId', $invoice->id)->pluck('tier');
    expect($lines)->toContain(TierPricing::TIER_CLAIM_SUBMISSION);
    expect($lines)->toContain(TierPricing::TIER_AI_AUTO_CODING);
    expect($lines)->toContain(TierPricing::TIER_FULL_SERVICE);
});

it('excludes cases not in the billing period', function () {
    $clinic = Clinic::factory()->selfCoded()->create();

    $oldCase = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-OLD',
        'clinic' => $clinic->id,
        'clinicName' => $clinic->name,
        'patient' => 'Prior Month',
        'state' => 'CA',
        'status' => 'delivered',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
    ]);
    $oldCase->forceFill(['updatedAt' => CarbonImmutable::parse('2026-02-15')])->saveQuietly();

    makeBillableCase($clinic->id, $clinic->name);                // in period

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);
    expect($invoice->claimCount)->toBe(1);
});

it('excludes non-completed cases (processing, needsReview, etc.)', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name, status: 'processing');
    makeBillableCase($clinic->id, $clinic->name, status: 'needsReview');
    makeBillableCase($clinic->id, $clinic->name, status: 'completed');

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);
    expect($invoice->claimCount)->toBe(1);
});

it('is idempotent on (clinicId, period) and re-runs do not duplicate lines', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name);

    $inv1 = $this->service->generateForClinicAndPeriod($clinic, $this->period);
    $inv2 = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($inv1->id)->toBe($inv2->id);
    expect(Invoice::where('clinicId', $clinic->id)->count())->toBe(1);
    expect(InvoiceLine::where('invoiceId', $inv1->id)->count())->toBe(1);
});

it('does not modify an invoice that has already been sent', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name);

    $inv = $this->service->generateForClinicAndPeriod($clinic, $this->period);
    $inv->forceFill(['status' => 'sent', 'total' => 999.00])->save();

    makeBillableCase($clinic->id, $clinic->name);                // add a new case

    $inv2 = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($inv2->id)->toBe($inv->id);
    expect((float) $inv2->total)->toBe(999.00);                  // NOT recomputed
});

it('uses correct invoice date (1st of next month) and due date (10th of next month)', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name);

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);

    expect($invoice->invoiceDate->toDateString())->toBe('2026-04-01');
    expect($invoice->dueDate->toDateString())->toBe('2026-04-10');
});

it('generates a stable invoice number format RC-{period}-{clinicShortId}', function () {
    $clinic = Clinic::factory()->selfCoded()->create();
    makeBillableCase($clinic->id, $clinic->name);

    $invoice = $this->service->generateForClinicAndPeriod($clinic, $this->period);
    expect($invoice->number)->toMatch('/^RC-2026-03-[A-F0-9]{7}$/i');
});
