<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * icd10_codes — ICD-10-CM reference data. CMS publishes annually on Oct 1.
 * Versioned by (code, effectiveFrom) like CPT codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10);
            $table->string('description', 500);
            $table->string('chapter', 100)->nullable();      // e.g., "M00-M99 Musculoskeletal"
            $table->date('effectiveFrom');
            $table->date('effectiveTo')->nullable();
            $table->boolean('billable')->default(true);      // non-billable headers (e.g. M54) vs billable leaves (M54.41)
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['code', 'effectiveFrom']);
            $table->index('code');
            $table->index('chapter');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_codes');
    }
};
