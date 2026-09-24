<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cases — PHI (the biggest PHI table in the system)
 *
 * The core working entity. Every coder action, every AI pipeline run,
 * every invoice line originates here. The table is named `cases` (matching
 * JSX and URL routes /api/cases); the Eloquent model is MedicalCase
 * because `Case` is a PHP reserved word.
 *
 * Columns `suggestedCPT`, `suggestedDX`, `modifiers`, `audit`, `timeline`,
 * `emails`, `stateCompliance` are stored as JSON — they're snapshots from
 * the AI pipeline, not normalized data. If downstream needs arise (e.g.
 * "find all cases where CPT 99213 was suggested"), split them into their
 * own tables at that point. Design choice logged in STEP-2-NOTES.md.
 *
 * `notes` is the full clinician-note text. Large — MEDIUMTEXT.
 * `svcs` is a JSON array of service IDs the clinic ordered for this case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('num', 20)->unique();             // "SNP-0001" display number
            $table->uuid('clinic');                          // tenant FK — JSX name preserved
            $table->string('clinicName');                    // denormalized for display (JSX has this)
            $table->uuid('patientId')->nullable();
            $table->uuid('providerId')->nullable();
            $table->uuid('assignedCoderId')->nullable();     // the user currently coding (may be Rocket admin or clinic coder)
            $table->string('patient');                       // PHI — denormalized display name from JSX
            $table->date('dob')->nullable();                 // PHI
            $table->date('dos')->nullable();                 // date of service — PHI
            $table->string('gender', 20)->nullable();
            $table->string('claimNum', 50)->nullable();      // PHI
            $table->string('emrId', 50)->nullable();         // PHI
            $table->string('state', 2);                      // US state where service rendered (drives fee schedule)
            $table->string('status', 40)->default('processing'); // CaseStatus enum value
            $table->string('reportType', 20);                // ReportType enum value
            $table->string('inputSource', 20);               // InputSource enum value
            $table->json('svcs')->nullable();                // service IDs ordered
            $table->decimal('total', 12, 2)->default(0);     // total clinic will be billed for this case
            $table->mediumText('notes')->nullable();         // PHI — full clinician notes
            $table->json('suggestedCPT')->nullable();        // AI output: [{code, desc, reasoning, confidence, citation}]
            $table->json('suggestedDX')->nullable();         // AI output: [{code, desc, primary, confidence}]
            $table->json('modifiers')->nullable();           // AI output: [{code, desc, appliedTo, stateRule}]
            $table->json('stateCompliance')->nullable();     // AI output: {compliant, system, note, violations}
            $table->json('emails')->nullable();              // [{to, status, ts}]
            $table->json('audit')->nullable();               // [{action, detail, ts}]
            $table->json('timeline')->nullable();            // [{label, date}]
            $table->unsignedInteger('aiTokensUsed')->default(0);        // Step 6 budget tracking
            $table->unsignedInteger('aiTokenBudget')->default(50000);   // hard cap per case
            $table->timestamp('aiCompletedAt')->nullable();
            $table->timestamp('deliveredAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinic')
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

            $table->foreign('assignedCoderId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('clinic');
            $table->index('status');
            $table->index(['clinic', 'status']);            // launcher-required compound index
            $table->index(['clinic', 'reportType']);
            $table->index('assignedCoderId');
            $table->index('state');
            $table->index('num');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
