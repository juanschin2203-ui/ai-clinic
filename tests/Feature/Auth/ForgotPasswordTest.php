<?php

declare(strict_types=1);

use App\Events\Auth\PasswordResetRequested;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()->inClinic($this->clinic)->create(['email' => 'reset@example.com']);
});

it('returns 200 with a generic message regardless of whether email exists', function () {
    $realEmailResponse = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);
    $fakeEmailResponse = $this->postJson('/api/auth/forgot-password', ['email' => 'does-not-exist@example.com']);

    // Both return 200 with the SAME message — no enumeration leak.
    $realEmailResponse->assertStatus(200);
    $fakeEmailResponse->assertStatus(200);

    expect($realEmailResponse->json('message'))->toBe($fakeEmailResponse->json('message'));
});

it('dispatches PasswordResetRequested event when email matches an active user', function () {
    Event::fake([PasswordResetRequested::class]);

    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(200);

    Event::assertDispatched(PasswordResetRequested::class, function ($event) {
        return $event->user->email === 'reset@example.com'
            && strlen($event->resetToken) === 64;
    });
});

it('does not dispatch event when email does not match any active user', function () {
    Event::fake([PasswordResetRequested::class]);

    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(200);

    Event::assertNotDispatched(PasswordResetRequested::class);
});

it('does not dispatch event when the user is inactive', function () {
    Event::fake([PasswordResetRequested::class]);

    $this->user->update(['active' => false]);

    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(200);

    Event::assertNotDispatched(PasswordResetRequested::class);
});

it('writes a reset token hash into password_reset_tokens', function () {
    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertStatus(200);

    $row = DB::table('password_reset_tokens')->where('email', 'reset@example.com')->first();

    expect($row)->not->toBeNull();
    expect($row->token)->not->toBeEmpty();
});
