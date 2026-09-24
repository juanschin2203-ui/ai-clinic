<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sanctum's personal_access_tokens — customized for Rocket Coding:
        //   - tokenable_id is UUID (our User model uses UUID PK)
        //   - tokens are short-lived (15 min) access tokens; refresh tokens
        //     live in our separate refresh_tokens table
        //   - column names stay snake_case because Sanctum's internals hard-
        //     code them; this is the one table that's an exception to our
        //     camelCase convention (documented in STEP-4-NOTES.md)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
