<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs — system-level events (distinct from audit_logs which is PHI-
 * centric). Drives the Deployer's Audit tab views for system health.
 *
 * Examples: AI pipeline started, reference-data file uploaded, invoice cycle
 * run, clinic deactivated, EMR sync failure, queue job failed. No PHI should
 * ever land here — the payload is operational metadata only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('userId')->nullable();              // may be system-triggered
            $table->uuid('clinicId')->nullable();            // may be global
            $table->string('category', 50);                  // "ai" | "billing" | "emr" | "ops" | "admin" | "deployer"
            $table->string('event', 100);                    // "ai.pipeline.completed", "billing.cycle.ran"
            $table->string('severity', 10)->default('info'); // info | warning | error | critical
            $table->string('message', 500);
            $table->json('context')->nullable();             // structured payload
            $table->timestamp('occurredAt');
            $table->timestamp('createdAt')->nullable();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->nullOnDelete();

            $table->index('userId');
            $table->index('clinicId');
            $table->index('category');
            $table->index(['category', 'event']);
            $table->index('severity');
            $table->index('occurredAt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
