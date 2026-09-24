<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cpt_codes — CPT reference data. AMA publishes annually, so we version by
 * (code, effectiveFrom) rather than using the code alone as PK. `effectiveTo`
 * null means currently in force.
 *
 * Loaded from JSX CPT_LOOKUP (seed) and extended from AMA releases uploaded
 * by the Deployer in Step 5's reference-data pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpt_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10);
            $table->string('description', 500);
            $table->string('category', 100)->nullable();
            $table->date('effectiveFrom');
            $table->date('effectiveTo')->nullable();
            $table->decimal('rvuWork', 8, 3)->nullable();    // work RVU — Medicare formula
            $table->decimal('rvuPe', 8, 3)->nullable();      // practice-expense RVU
            $table->decimal('rvuMp', 8, 3)->nullable();      // malpractice RVU
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['code', 'effectiveFrom']);
            $table->index('code');
            $table->index('category');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpt_codes');
    }
};
