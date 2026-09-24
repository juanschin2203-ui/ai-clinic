<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emr_connections — a specific clinic's wiring to an EMR system. Holds the
 * endpoint, credentials reference, and health status. Credentials themselves
 * are NOT stored here — `credentialsRef` points to a row in a secrets store
 * (AWS Secrets Manager / Vault) keyed by the clinic + EMR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emr_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinicId');
            $table->uuid('emrSystemId');
            $table->string('status', 20)->default('notConnected');
            $table->string('endpoint', 500)->nullable();
            $table->string('credentialsRef')->nullable();    // secrets-manager key ref (never the secret itself)
            $table->timestamp('lastSync')->nullable();
            $table->timestamp('lastTestOk')->nullable();
            $table->text('lastError')->nullable();
            $table->json('syncFields')->nullable();          // which fields this clinic chose to sync
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinicId')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->foreign('emrSystemId')
                ->references('id')
                ->on('emr_systems')
                ->cascadeOnDelete();

            $table->unique(['clinicId', 'emrSystemId']);
            $table->index('clinicId');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emr_connections');
    }
};
