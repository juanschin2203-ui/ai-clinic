<?php

declare(strict_types=1);

use App\Jobs\Billing\GenerateClinicInvoice;
use App\Jobs\Billing\RunMonthlyBillingCycle;
use App\Jobs\Billing\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Clinic;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

it('RunMonthlyBillingCycle fans out one GenerateClinicInvoice per active clinic', function () {
    Bus::fake();

    Clinic::factory()->count(3)->create(['active' => true]);
    Clinic::factory()->create(['active' => false]);         // inactive — should NOT get a job

    (new RunMonthlyBillingCycle('2026-03'))->handle();

    Bus::assertDispatchedTimes(GenerateClinicInvoice::class, 3);
});

it('RunMonthlyBillingCycle defaults to the prior calendar month when no period is passed', function () {
    Bus::fake();

    Clinic::factory()->create(['active' => true]);

    // Freeze time to mid-April so prior-month is 2026-03.
    $now = CarbonImmutable::parse('2026-04-15 03:00:00');
    CarbonImmutable::setTestNow($now);

    (new RunMonthlyBillingCycle())->handle();

    Bus::assertDispatched(GenerateClinicInvoice::class, function ($job) {
        return $job->period === '2026-03';
    });

    CarbonImmutable::setTestNow();
});

it('GenerateClinicInvoice dispatches SendInvoiceEmail after the invoice lands in draft', function () {
    Bus::fake([SendInvoiceEmail::class]);

    $clinic = Clinic::factory()->create(['active' => true, 'selfCoded' => true]);

    (new GenerateClinicInvoice($clinic->id, '2026-03'))->handle(
        app(\App\Services\Billing\InvoiceGenerationService::class),
    );

    Bus::assertDispatched(SendInvoiceEmail::class, function ($job) use ($clinic) {
        $invoice = Invoice::where('clinicId', $clinic->id)->first();

        return $invoice !== null && $job->invoiceId === $invoice->id;
    });
});

it('SendInvoiceEmail sends the mailable and flips status from draft to sent', function () {
    Mail::fake();

    $clinic = Clinic::factory()->create(['email' => 'billing@testclinic.com']);
    $invoice = Invoice::query()->create([
        'clinicId' => $clinic->id,
        'period' => '2026-03',
        'status' => 'draft',
        'subtotal' => 10.00,
        'tax' => 0,
        'total' => 10.00,
        'claimCount' => 5,
        'aiCount' => 1,
        'fullServiceCount' => 0,
        'invoiceDate' => '2026-04-01',
        'dueDate' => '2026-04-10',
        'billingMethod' => 'ach',
        'number' => 'RC-2026-03-TEST123',
    ]);

    (new SendInvoiceEmail($invoice->id))->handle();

    // InvoiceMail implements ShouldQueue, so Laravel's Mail facade
    // routes it through the queue, not the synchronous send path.
    Mail::assertQueued(InvoiceMail::class, function ($mail) {
        return $mail->hasTo('billing@testclinic.com');
    });

    expect($invoice->refresh()->status)->toBe('sent');
    expect($invoice->sentAt)->not->toBeNull();
});

it('SendInvoiceEmail does not re-send an already-sent invoice', function () {
    Mail::fake();

    $clinic = Clinic::factory()->create(['email' => 'billing@testclinic.com']);
    $invoice = Invoice::query()->create([
        'clinicId' => $clinic->id,
        'period' => '2026-03',
        'status' => 'sent',                    // already sent
        'subtotal' => 5.00, 'tax' => 0, 'total' => 5.00,
        'claimCount' => 2, 'aiCount' => 0, 'fullServiceCount' => 0,
        'invoiceDate' => '2026-04-01', 'dueDate' => '2026-04-10',
        'billingMethod' => 'ach', 'number' => 'RC-2026-03-DUPTEST',
    ]);

    (new SendInvoiceEmail($invoice->id))->handle();

    Mail::assertNothingQueued();
});
