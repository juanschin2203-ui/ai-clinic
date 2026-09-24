<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fee_schedules — WC fee schedule per US state. Matches JSX FEE_STATES (line 86).
 * PK is the 2-letter state code to preserve the JSX reference style.
 *
 * `sys` is the state system abbreviation: OMFS (CA), TDI (TX), WCB (NY),
 * DWC (FL), WCC (IL). Extendable when new states are onboarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->string('code', 2)->primary();   // "CA", "TX", "NY", "FL", "IL"
            $table->string('name');                 // "California"
            $table->string('sys', 20);              // "OMFS"
            $table->decimal('m', 6, 4);             // state multiplier (1.0000, 0.9200, ...)
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};
