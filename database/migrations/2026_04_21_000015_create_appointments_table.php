<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * appointments — PHI (patient name)
 *
 * Matches JSX APPOINTMENTS (line 33). The JSX has string `provider`/`patient`
 * names; we store them AND keep FKs to provider/patient IDs for proper
 * relational lookups. `dictation` and `chartUploaded` are booleans indicating
 * whether those inputs have been captured at appointment time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinicId');
            $table->uuid('patientId')->nullable();
            $table->uuid('providerId')->nullable();
            $table->date('date');
            $table->time('time');
            $table->string('patient')->nullable();           // denormalized display name (PHI) — matches JSX
            $table->string('provider')->nullable();          // denormalized display name
            $table->string('company')->nullable();           // "Valley Medical Group" display
            $table->string('visitType', 30);                 // report-type key: initial, mmi, qme, followup, causation, procedure
            $table->string('claim', 50)->nullable();         // PHI
            $table->boolean('dictation')->default(false);    // has dictation been captured?
            $table->boolean('chartUploaded')->default(false);// has chart been uploaded?
            $table->string('status', 20)->default('scheduled'); // scheduled | checkedIn | complete | noshow | cancelled
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->foreign('patientId')
                ->references('id')
                ->on('patients')
                ->nullOnDelete();

            $table->foreign('providerId')
                ->references('id')
                ->on('providers')
                ->nullOnDelete();

            $table->index('clinicId');
            $table->index(['clinicId', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
