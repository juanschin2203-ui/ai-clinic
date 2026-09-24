<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('pw123')
        ->create(['email' => 'audit@example.com']);
});

it('writes an audit_logs row on successful login', function () {
    $before = AuditLog::where('action', 'login')->count();

    $this->postJson('/api/auth/login', [
        'email' => 'audit@example.com',
        'password' => 'pw123',
    ])->assertStatus(200);

    expect(AuditLog::where('action', 'login')->count())->toBe($before + 1);

    $row = AuditLog::where('action', 'login')
        ->where('userId', $this->user->id)
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->metadata['outcome'] ?? null)->toBe('success');
    expect($row->isPhiAccess)->toBeFalse();
});

it('writes an audit_logs row on failed login, with failure reason', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'audit@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);

    $row = AuditLog::where('action', 'login')
        ->where('userId', $this->user->id)
        ->latest('occurredAt')
        ->first();

    expect($row->metadata['outcome'] ?? null)->toBe('failure');
    expect($row->metadata['reason'] ?? null)->toBe('bad_password');
});

it('writes an audit_logs row on login failure with unknown email', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'ghost@example.com',
        'password' => 'anything',
    ])->assertStatus(401);

    $row = AuditLog::where('action', 'login')
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->userId)->toBeNull();                      // user didn't exist
    expect($row->metadata['reason'] ?? null)->toBe('user_not_found');
    expect($row->metadata['email'] ?? null)->toBe('ghost@example.com');
});

it('writes an audit_logs row on logout', function () {
    $login = $this->postJson('/api/auth/login', [
        'email' => 'audit@example.com',
        'password' => 'pw123',
    ]);
    $accessToken = $login->json('data.accessToken');

    $this->withToken($accessToken)->postJson('/api/auth/logout')->assertStatus(204);

    expect(
        AuditLog::where('action', 'logout')->where('userId', $this->user->id)->count()
    )->toBe(1);
});
