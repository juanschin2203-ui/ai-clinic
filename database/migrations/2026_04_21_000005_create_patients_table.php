<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * patients — PHI
 *
 * Every column below except id/timestamps/FKs is PHI. Reads and writes MUST
 * be logged via the PhiAuditService (Step 4). Tenant scope is enforced via
 * a global scope on the Patient model — clinic users can never see a
 * patient belonging to another clinic.
 *
 * The JSX PATIENTS array has no clinic FK (`provider` is a display string);
 * we add `clinicId` and `providerId` here because the backend needs real
 * tenancy and attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinicId');
            $table->uuid('providerId')->nullable();
            $table->string('name');                          // PHI
            $table->date('dob')->nullable();                 // PHI
            $table->string('gender', 20)->nullable();
            $table->string('emrId')->nullable();             // PHI
            $table->string('phone', 50)->nullable();         // PHI
            $table->string('email')->nullable();             // PHI
            $table->string('employer')->nullable();
            $table->date('doi')->nullable();                 // date of injury
            $table->string('claimNum')->nullable();          // PHI
            $table->string('reportStatus', 30)->default('needs_report');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->foreign('providerId')
                ->references('id')
                ->on('providers')
                ->nullOnDelete();

            $table->index('clinicId');
            $table->index(['clinicId', 'reportStatus']);
            $table->index('emrId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
