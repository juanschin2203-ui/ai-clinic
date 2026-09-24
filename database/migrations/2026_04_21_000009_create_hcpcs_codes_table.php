<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hcpcs_codes — HCPCS Level II reference data (durable medical equipment,
 * supplies, drugs administered in-office). CMS publishes quarterly updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcpcs_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10);
            $table->string('description', 500);
            $table->string('category', 100)->nullable();
            $table->date('effectiveFrom');
            $table->date('effectiveTo')->nullable();
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
        Schema::dropIfExists('hcpcs_codes');
    }
};
