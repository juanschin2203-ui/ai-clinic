<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\RefreshToken;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('correct-horse')
        ->create(['email' => 'refresh@example.com']);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'refresh@example.com',
        'password' => 'correct-horse',
    ]);
    $this->originalRefresh = $login->json('data.refreshToken');
    $this->originalAccess = $login->json('data.accessToken');
});

it('issues a new pair on valid refresh', function () {
    $response = $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['accessToken', 'refreshToken', 'refreshTokenExpiresAt'],
        ]);

    $newRefresh = $response->json('data.refreshToken');
    expect($newRefresh)->not->toBe($this->originalRefresh);
});

it('marks the old refresh token as used after rotation', function () {
    $beforeCount = RefreshToken::whereNotNull('usedAt')->count();

    $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ])->assertStatus(200);

    expect(RefreshToken::whereNotNull('usedAt')->count())->toBe($beforeCount + 1);
});

it('links the old token to the new one via replacedByTokenId', function () {
    $response = $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ])->assertStatus(200);

    $oldHash = hash('sha256', $this->originalRefresh);
    $oldToken = RefreshToken::where('tokenHash', $oldHash)->first();

    expect($oldToken->replacedByTokenId)->not->toBeNull();
});

it('rejects a used refresh token and revokes all user sessions (theft defense)', function () {
    // Legitimate refresh
    $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ])->assertStatus(200);

    // Attacker presents the now-used refresh token
    $attackResponse = $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ]);

    $attackResponse
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_refresh_token');

    // All of this user's active refresh tokens should now be revoked.
    // (The legitimately-refreshed-into token also gets revoked as break-glass.)
    $activeCount = RefreshToken::where('userId', $this->user->id)
        ->whereNull('revokedAt')
        ->whereNull('usedAt')
        ->count();

    expect($activeCount)->toBe(0);
});

it('rejects an unknown refresh token', function () {
    $this->postJson('/api/auth/refresh', [
        'refreshToken' => str_repeat('a', 64),
    ])->assertStatus(401);
});

it('rejects a refresh token from an inactive user', function () {
    $this->user->update(['active' => false]);

    $this->postJson('/api/auth/refresh', [
        'refreshToken' => $this->originalRefresh,
    ])->assertStatus(403);
});
