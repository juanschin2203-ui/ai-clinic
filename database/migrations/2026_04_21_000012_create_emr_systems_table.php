<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emr_systems — catalog of supported EMRs (Epic, athenahealth, eClinicalWorks,
 * etc.). Matches JSX EMR_SYSTEMS (line 93). Reference data. Not tenant-scoped.
 * `fields` is the list of data domains this EMR exposes through the integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emr_systems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('type', 50);                     // "fhir", "rest", "sftp", "api"
            $table->string('typeLabel')->nullable();        // "API (FHIR R4)" — display string from JSX
            $table->string('icon', 10)->nullable();         // emoji icon from JSX
            $table->json('fields')->nullable();             // ["Demographics","Encounters",...]
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index('type');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emr_systems');
    }
};
