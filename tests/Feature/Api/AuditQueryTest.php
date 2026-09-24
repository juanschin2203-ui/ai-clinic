<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->clinicUser = User::factory()->inClinic($this->clinic)->withPassword('pw123')->create();
    $this->admin = User::factory()->admin()->withPassword('pw123')->create();
    $this->deployer = User::factory()->deployer()->withPassword('pw123')->create();
});

function loginAsUser(\App\Models\User $user): string
{
    $res = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'pw123']);

    return $res->json('data.accessToken');
}

it('rejects audit endpoints for clinic-role users', function () {
    $token = loginAsUser($this->clinicUser);

    $this->withToken($token)->getJson('/api/audit/hipaa')->assertStatus(403);
    $this->withToken($token)->getJson('/api/audit/soc2')->assertStatus(403);
    $this->withToken($token)->getJson('/api/audit/activity')->assertStatus(403);
});

it('returns HIPAA audit rows for admin filtered to isPhiAccess=true', function () {
    AuditLog::query()->create([
        'userId' => $this->clinicUser->id,
        'clinicId' => $this->clinic->id,
        'action' => 'read',
        'entityType' => 'patient',
        'entityId' => (string) \Illuminate\Support\Str::uuid(),
        'isPhiAccess' => true,
        'occurredAt' => CarbonImmutable::now(),
    ]);
    AuditLog::query()->create([
        'userId' => $this->clinicUser->id,
        'clinicId' => $this->clinic->id,
        'action' => 'login',
        'entityType' => 'user',
        'entityId' => $this->clinicUser->id,
        'isPhiAccess' => false,                          // NOT a PHI row
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = loginAsUser($this->admin);
    $response = $this->withToken($token)->getJson('/api/audit/hipaa');

    $response
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['action', 'isPhiAccess', 'occurredAt']], 'meta' => ['total', 'perPage']]);

    // All returned rows should have isPhiAccess=true
    $flags = collect($response->json('data'))->pluck('isPhiAccess')->unique();
    expect($flags->all())->toBe([true]);
});

it('returns SOC 2 audit (all audit_logs rows) for deployer', function () {
    AuditLog::query()->create([
        'userId' => $this->admin->id,
        'clinicId' => null,
        'action' => 'login',
        'entityType' => 'user',
        'entityId' => $this->admin->id,
        'isPhiAccess' => false,
        'occurredAt' => CarbonImmutable::now(),
    ]);
    AuditLog::query()->create([
        'userId' => $this->clinicUser->id,
        'clinicId' => $this->clinic->id,
        'action' => 'read',
        'entityType' => 'patient',
        'entityId' => (string) \Illuminate\Support\Str::uuid(),
        'isPhiAccess' => true,
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = loginAsUser($this->deployer);
    $response = $this->withToken($token)->getJson('/api/audit/soc2');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
});

it('filters audit queries by clinicId', function () {
    $otherClinic = Clinic::factory()->create();

    AuditLog::query()->create([
        'clinicId' => $this->clinic->id,
        'action' => 'read',
        'entityType' => 'patient',
        'entityId' => (string) \Illuminate\Support\Str::uuid(),
        'isPhiAccess' => true,
        'occurredAt' => CarbonImmutable::now(),
    ]);
    AuditLog::query()->create([
        'clinicId' => $otherClinic->id,
        'action' => 'read',
        'entityType' => 'patient',
        'entityId' => (string) \Illuminate\Support\Str::uuid(),
        'isPhiAccess' => true,
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = loginAsUser($this->admin);
    $response = $this->withToken($token)->getJson("/api/audit/hipaa?clinicId={$this->clinic->id}");

    $response->assertStatus(200);
    foreach ($response->json('data') as $row) {
        expect($row['clinicId'])->toBe($this->clinic->id);
    }
});

it('returns system activity log for deployer', function () {
    ActivityLog::query()->create([
        'category' => 'ai',
        'event' => 'ai.pipeline.completed',
        'severity' => 'info',
        'message' => 'Pipeline finished for case X',
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = loginAsUser($this->deployer);
    $response = $this->withToken($token)->getJson('/api/audit/activity?category=ai');

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.0.category', 'ai')
        ->assertJsonPath('data.0.event', 'ai.pipeline.completed');
});
