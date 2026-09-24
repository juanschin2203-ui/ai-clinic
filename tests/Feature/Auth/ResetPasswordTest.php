<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('old-password')
        ->create(['email' => 'reset@example.com']);

    $this->resetService = app(PasswordResetService::class);
    $this->rawToken = $this->resetService->issue($this->user);
});

it('updates the password when token is valid', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $this->rawToken,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(200);
    expect(Hash::check('new-password-123', $this->user->refresh()->password))->toBeTrue();
    expect(Hash::check('old-password', $this->user->refresh()->password))->toBeFalse();
});

it('clears lockout state on successful reset', function () {
    $this->user->forceFill([
        'loginAttempts' => 3,
        'lockedUntil' => now()->addMinutes(10),
    ])->save();

    $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $this->rawToken,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(200);

    expect($this->user->refresh()->loginAttempts)->toBe(0);
    expect($this->user->refresh()->lockedUntil)->toBeNull();
});

it('returns 422 with generic error on bad token', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => str_repeat('x', 64),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_reset_token');

    // Original password still works
    expect(Hash::check('old-password', $this->user->refresh()->password))->toBeTrue();
});

it('rejects a used token (consume is single-shot)', function () {
    // First use — success
    $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $this->rawToken,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(200);

    // Second use of the same token — fails
    $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $this->rawToken,
        'password' => 'different-password',
        'password_confirmation' => 'different-password',
    ])->assertStatus(422);
});

it('enforces password confirmation match', function () {
    $response = $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $this->rawToken,
        'password' => 'new-password-123',
        'password_confirmation' => 'different',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
