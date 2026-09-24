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
        ->create();

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse',
    ]);
    $this->accessToken = $login->json('data.accessToken');
    $this->refreshToken = $login->json('data.refreshToken');
});

it('returns 401 when no bearer token is provided', function () {
    $this->postJson('/api/auth/logout')->assertStatus(401);
});

it('revokes the refresh token on logout when provided', function () {
    $this->withToken($this->accessToken)
        ->postJson('/api/auth/logout', ['refreshToken' => $this->refreshToken])
        ->assertStatus(204);

    $hash = hash('sha256', $this->refreshToken);
    $token = RefreshToken::where('tokenHash', $hash)->first();

    expect($token->revokedAt)->not->toBeNull();
});

it('revokes all refresh tokens when no specific one is provided', function () {
    // Issue two more sessions for this user
    $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse',
    ]);
    $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse',
    ]);

    expect(RefreshToken::where('userId', $this->user->id)->whereNull('revokedAt')->count())->toBe(3);

    $this->withToken($this->accessToken)
        ->postJson('/api/auth/logout')
        ->assertStatus(204);

    expect(RefreshToken::where('userId', $this->user->id)->whereNull('revokedAt')->count())->toBe(0);
});
