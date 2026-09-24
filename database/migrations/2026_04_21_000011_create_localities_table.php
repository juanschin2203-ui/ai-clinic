<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * localities — geographic adjusters within a fee schedule. Matches the
 * `localities` array nested inside each FEE_STATES entry in JSX line 86.
 * A city/region gets a multiplier applied on top of the state multiplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('feeScheduleCode', 2);
            $table->string('code', 10);                 // "18", "99" (rest of state), etc.
            $table->string('name');                     // "Los Angeles"
            $table->decimal('adj', 6, 4);               // geographic adjustment (0.9460 – 1.0940 range)
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('feeScheduleCode')
                ->references('code')
                ->on('fee_schedules')
                ->cascadeOnDelete();

            $table->unique(['feeScheduleCode', 'code']);
            $table->index('feeScheduleCode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localities');
    }
};
