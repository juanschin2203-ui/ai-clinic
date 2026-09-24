<?php

declare(strict_types=1);

use App\Mail\PasswordResetMail;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('queues a PasswordResetMail when forgot-password is hit for an active user', function () {
    Mail::fake();

    $clinic = Clinic::factory()->create();
    $user = User::factory()->inClinic($clinic)->create(['email' => 'reset@example.com']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(200);

    Mail::assertQueued(PasswordResetMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && strlen($mail->resetToken) === 64;
    });
});

it('does not queue a PasswordResetMail for an unknown email', function () {
    Mail::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(200);

    Mail::assertNothingQueued();
});

it('does not queue a PasswordResetMail for an inactive user', function () {
    Mail::fake();

    $clinic = Clinic::factory()->create();
    User::factory()->inClinic($clinic)->inactive()->create(['email' => 'inactive@example.com']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'inactive@example.com'])
        ->assertStatus(200);

    Mail::assertNothingQueued();
});
