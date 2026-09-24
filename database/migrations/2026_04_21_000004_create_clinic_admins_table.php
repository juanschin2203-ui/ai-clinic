<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clinic_admins — contact records for clinic admins (office manager, billing lead).
 * Separate from `users` because a clinic admin may not have a login (email-only
 * contact) and vice versa. The prototype keeps these in its own CLINIC_ADMINS array.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinic');
            $table->string('name');
            $table->string('email');
            $table->string('role')->default('Clinic Admin');
            $table->date('lastLogin')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('clinic')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();

            $table->index('clinic');
            $table->unique(['clinic', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_admins');
    }
};
