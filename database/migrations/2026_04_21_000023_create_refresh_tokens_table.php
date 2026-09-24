<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * refresh_tokens — long-lived tokens used to mint short-lived Sanctum access
 * tokens. 7-day TTL by default (SANCTUM_REFRESH_TOKEN_TTL).
 *
 * We store `tokenHash` (SHA-256 hex), NEVER the raw token. On refresh:
 *   1. Look up row by hash
 *   2. Verify not revoked, not used, not expired
 *   3. Mark old row used=now, issue a new access token AND a new refresh token
 *   4. Return only the new pair to the client
 *
 * This is token rotation. A stolen refresh token becomes useless the moment
 * the legitimate user refreshes (the stolen one is now marked used).
 *
 * Step 4 implements the login + refresh flow that populates and rotates this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('userId');
            $table->string('tokenHash', 64)->unique();       // SHA-256 hex
            $table->timestamp('issuedAt');
            $table->timestamp('expiresAt');
            $table->timestamp('usedAt')->nullable();         // rotation marker — null = fresh, set = spent
            $table->timestamp('revokedAt')->nullable();      // explicit logout or admin revoke
            $table->uuid('replacedByTokenId')->nullable();   // forward-chain for audit
            $table->string('ipAddress', 45)->nullable();
            $table->text('userAgent')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('replacedByTokenId')
                ->references('id')
                ->on('refresh_tokens')
                ->nullOnDelete();

            $table->index('userId');
            $table->index('expiresAt');
            $table->index(['userId', 'revokedAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
