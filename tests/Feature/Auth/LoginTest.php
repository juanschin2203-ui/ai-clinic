<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('correct-horse')
        ->create(['email' => 'test@example.com']);
});

it('returns 200 with token pair and user on valid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'correct-horse',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'accessToken',
                'accessTokenExpiresAt',
                'refreshToken',
                'refreshTokenExpiresAt',
                'user' => ['id', 'name', 'email', 'role', 'cid', 'cn', 'initials', 'permission', 'active', 'lastLogin'],
            ],
        ])
        ->assertJsonPath('data.user.email', 'test@example.com')
        ->assertJsonPath('data.user.cid', $this->clinic->id);
});

it('returns 401 on invalid password', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ]);

    $response
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('returns 401 on unknown email', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'anything',
    ]);

    $response
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('returns 403 when account is inactive', function () {
    $this->user->update(['active' => false]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'correct-horse',
    ]);

    $response
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'account_inactive');
});

it('validates email and password are required', function () {
    $this->postJson('/api/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('resets login attempts on successful login', function () {
    $this->user->forceFill(['loginAttempts' => 2])->save();

    $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'correct-horse',
    ])->assertStatus(200);

    expect($this->user->refresh()->loginAttempts)->toBe(0);
    expect($this->user->refresh()->lastLogin)->not->toBeNull();
});

it('increments login attempts on failed login', function () {
    expect($this->user->loginAttempts)->toBe(0);

    $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);

    expect($this->user->refresh()->loginAttempts)->toBe(1);
});
