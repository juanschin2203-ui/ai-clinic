<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_methods — a clinic's saved ACH bank account or credit card.
 *
 * Raw PAN / bank account numbers are NEVER stored here. Only a tokenized
 * reference from the payment provider (Stripe, Plaid, Dwolla) plus the
 * display-safe last-4 and brand/bank name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinicId');
            $table->string('method', 10);                    // BillingMethod: ach | card
            $table->string('provider', 20);                  // "stripe" | "plaid" | "dwolla"
            $table->string('providerRef', 100);              // provider-side token (pm_..., ba_...)
            $table->string('brand', 30)->nullable();         // "Visa" | "Chase" | "Bank of America"
            $table->string('last4', 4)->nullable();
            $table->string('holderName')->nullable();
            $table->boolean('isDefault')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('verifiedAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->index('clinicId');
            $table->index(['clinicId', 'isDefault']);
            $table->index(['clinicId', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
