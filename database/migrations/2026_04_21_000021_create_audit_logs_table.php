<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs — HIPAA-grade audit trail. EVERY read, write, export, and delete
 * of PHI lands here. This table is:
 *
 *   - append-only (no updates, no deletes — row-level DB policy in prod)
 *   - enforced at the ORM layer via Eloquent observers and a dedicated
 *     PhiAuditService, NOT sprinkled through controllers
 *   - queried by the Deployer's Audit tab (Step 5) to generate SOC 2 / HIPAA
 *     compliance reports
 *
 * `entityType` + `entityId` point at the touched row (patient, medical_case,
 * file, etc.). Polymorphic — not a hard FK — because the target may be
 * soft-deleted but audit must persist.
 *
 * `before` / `after` hold JSON diffs on writes; NULL on reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('userId')->nullable();              // nullable for system-generated events
            $table->uuid('clinicId')->nullable();            // tenant context at the time of the action
            $table->string('action', 30);                    // "read" | "create" | "update" | "delete" | "export" | "print"
            $table->string('entityType', 50);                // "patient" | "medical_case" | "file" | "invoice" | ...
            $table->uuid('entityId')->nullable();
            $table->boolean('isPhiAccess')->default(false);  // true if the target is PHI — drives HIPAA reports
            $table->string('ipAddress', 45)->nullable();
            $table->text('userAgent')->nullable();
            $table->string('requestId', 40)->nullable();     // correlates audit rows with HTTP request ID
            $table->json('before')->nullable();              // snapshot of the row BEFORE mutation
            $table->json('after')->nullable();               // snapshot AFTER mutation
            $table->json('metadata')->nullable();            // free-form: endpoint, reason, etc.
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
            $table->index(['entityType', 'entityId']);
            $table->index('action');
            $table->index('isPhiAccess');
            $table->index('occurredAt');
            $table->index(['clinicId', 'occurredAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
