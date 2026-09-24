<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clinics — tenant root. Every tenant-scoped table FKs to this via `cid` / `clinic`
 * (preserving the prototype's JSX naming). selfCoded distinguishes We-Code clinics
 * from clinics whose cases Rocket Coding codes end-to-end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state', 2);
            $table->string('email');
            $table->boolean('active')->default(true);
            $table->string('city')->nullable();
            $table->string('addr')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('npi', 15)->nullable();
            $table->string('taxId', 15)->nullable();
            $table->string('timezone', 64)->default('America/Los_Angeles');
            $table->string('logo', 500)->nullable();
            $table->boolean('selfCoded')->default(false);
            $table->json('reportFavs')->nullable();
            $table->json('notif')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->index('state');
            $table->index('active');
            $table->index('selfCoded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
