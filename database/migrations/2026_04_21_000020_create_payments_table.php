<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payments — recorded payment events (ACH debit success, failure, refund, etc).
 * Each payment references an invoice; an invoice can have N payments (retries,
 * partial refunds). Immutable — every state transition is a new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoiceId');
            $table->uuid('clinicId');
            $table->uuid('paymentMethodId')->nullable();
            $table->string('status', 20);                    // "initiated" | "succeeded" | "failed" | "refunded"
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('providerRef', 100)->nullable();  // ACH trace ID / Stripe charge ID
            $table->string('failureCode', 50)->nullable();
            $table->string('failureMessage')->nullable();
            $table->timestamp('initiatedAt')->nullable();
            $table->timestamp('settledAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('invoiceId')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->restrictOnDelete();

            $table->foreign('paymentMethodId')
                ->references('id')
                ->on('payment_methods')
                ->nullOnDelete();

            $table->index('invoiceId');
            $table->index('clinicId');
            $table->index('status');
            $table->index('providerRef');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
