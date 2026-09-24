<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * services — reference list of billable services the clinic ordered from Rocket
 * Coding. Matches JSX SERVICES (line 42). Not tenant-scoped; globally shared.
 * The `id` column preserves the JSX "s1"/"s5"/"sT" string keys so that the
 * `cases.svcs` JSON array can reference them without remapping during seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->string('id', 10)->primary();   // "s1", "s16", "sT", etc. — string, not UUID
            $table->string('cat', 50);             // Coding & Billing | Clinical | Work Comp | Med-Legal
            $table->string('title');
            $table->decimal('price', 10, 2);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sortOrder')->default(0);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index('cat');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
