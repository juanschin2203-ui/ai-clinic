<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('correct-horse')
        ->create(['email' => 'lockout@example.com']);
});

it('locks the account after 3 consecutive failed logins', function () {
    // 3 wrong attempts
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    expect($this->user->refresh()->loginAttempts)->toBe(3);
    expect($this->user->refresh()->lockedUntil)->not->toBeNull();

    // 4th attempt, even with the correct password, is rejected with 423 Locked
    $response = $this->postJson('/api/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'correct-horse',
    ]);

    $response
        ->assertStatus(423)
        ->assertJsonPath('error.code', 'account_locked')
        ->assertJsonStructure(['error' => ['retryAfterSeconds']]);
});

it('clears lockout after cooldown expires', function () {
    // Force a locked state 20 minutes in the past (default cooldown is 15 min)
    $this->user->forceFill([
        'loginAttempts' => 3,
        'lockedUntil' => now()->subMinutes(5), // already expired
    ])->save();

    $this->postJson('/api/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'correct-horse',
    ])->assertStatus(200);

    expect($this->user->refresh()->loginAttempts)->toBe(0);
    expect($this->user->refresh()->lockedUntil)->toBeNull();
});

it('does not leak whether the password was correct while locked', function () {
    $this->user->forceFill([
        'loginAttempts' => 3,
        'lockedUntil' => now()->addMinutes(10),
    ])->save();

    // Wrong password while locked → 423 (not 401)
    $response1 = $this->postJson('/api/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'wrong',
    ]);

    // Correct password while locked → also 423
    $response2 = $this->postJson('/api/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'correct-horse',
    ]);

    $response1->assertStatus(423);
    $response2->assertStatus(423);
});
