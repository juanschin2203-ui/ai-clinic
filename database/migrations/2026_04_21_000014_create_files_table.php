<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * files — uploaded charts, dictation recordings, batch input files, invoice
 * PDFs, EMR exports. Uses a polymorphic owner (ownerType + ownerId) so a file
 * can attach to a clinic, a case, a patient, an invoice, etc.
 *
 * Chart uploads and dictation recordings contain PHI. `encryptionRef` points
 * at the KMS key used to encrypt the S3 object; stored for audit/forensics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinicId')->nullable();            // clinic scope; null for Rocket-admin-uploaded ref data
            $table->uuid('uploaderId')->nullable();          // the user who uploaded
            $table->string('ownerType', 50);                 // "medical_case", "patient", "clinic", "invoice"
            $table->uuid('ownerId');
            $table->string('kind', 30);                      // "chart", "dictation", "batch", "invoicePdf", "emrExport"
            $table->string('filename');
            $table->string('mimeType', 100);
            $table->unsignedBigInteger('sizeBytes');
            $table->string('s3Bucket')->nullable();
            $table->string('s3Key', 500);
            $table->string('checksum', 128)->nullable();     // sha256 hex
            $table->string('encryptionRef', 200)->nullable();// KMS key ARN or alias
            $table->boolean('containsPhi')->default(false);  // true for chart + dictation
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->foreign('uploaderId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['ownerType', 'ownerId']);
            $table->index('clinicId');
            $table->index('kind');
            $table->index('containsPhi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
