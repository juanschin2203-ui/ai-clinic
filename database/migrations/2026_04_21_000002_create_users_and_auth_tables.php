<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users + sessions + password_reset_tokens — all three in one migration because
 * sessions.userId FKs to users, and they're conceptually one auth bundle.
 *
 * users.cid is nullable because Rocket admins (role=admin) have no clinic.
 * Login-hardening columns (loginAttempts, lockedUntil) back Step 4's lockout
 * policy — 3 failures -> 15-minute lockout, per the user manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('emailVerifiedAt')->nullable();
            $table->string('password');
            $table->string('role', 20);
            $table->uuid('cid')->nullable();
            $table->string('initials', 10);
            $table->string('permission', 20)->default('viewer');
            $table->boolean('active')->default(true);
            $table->date('lastLogin')->nullable();
            $table->unsignedInteger('loginAttempts')->default(0);
            $table->timestamp('lockedUntil')->nullable();
            $table->string('rememberToken', 100)->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->foreign('cid')
                ->references('id')
                ->on('clinics')
                ->nullOnDelete();

            $table->index('role');
            $table->index(['cid', 'role']);
            $table->index(['cid', 'active']);
            $table->index('active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('createdAt')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('userId')->nullable()->index();
            $table->string('ipAddress', 45)->nullable();
            $table->text('userAgent')->nullable();
            $table->longText('payload');
            $table->integer('lastActivity')->index();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
