<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * invoice_lines — one row per charge on an invoice.
 *
 * Line-level granularity: each case contributes 1-3 lines to an invoice
 * (Claim Submission $1.75 always; AI Auto-Coding $1.00 if AI ran; Full-Service
 * Billing $5.00 if Rocket coded end-to-end). A single case can appear on one
 * invoice via multiple lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoiceId');
            $table->uuid('caseId')->nullable();              // nullable for non-per-case charges (adjustments)
            $table->string('tier', 30);                      // "claimSubmission" | "aiAutoCoding" | "fullServiceBilling" | "adjustment"
            $table->string('description', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unitPrice', 10, 2);
            $table->decimal('amount', 12, 2);                // quantity * unitPrice, snapshotted
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('invoiceId')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();

            $table->foreign('caseId')
                ->references('id')
                ->on('cases')
                ->nullOnDelete();                            // if case is deleted, keep the line for audit

            $table->index('invoiceId');
            $table->index('caseId');
            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
