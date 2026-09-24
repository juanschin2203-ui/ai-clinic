<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * providers — clinicians who sign reports. A provider *may* also have a User
 * login (userId nullable FK) for the provider-mobile view, but many providers
 * are entered as reference records only.
 *
 * Field name `clinic` (not `clinicId`) matches JSX PROVIDERS[].clinic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinic');
            $table->uuid('userId')->nullable();
            $table->string('name');
            $table->string('npi', 15);
            $table->string('license', 50);
            $table->string('specialty', 100);
            $table->text('sigBlock')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinic')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('clinic');
            $table->index(['clinic', 'active']);
            $table->index('npi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
