<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;

/**
 * The PHI audit trail must fire on every read and write of PHI-bearing
 * models. These tests prove the observer + controller dispatch wiring is
 * intact — this is the row-level HIPAA audit that SOC 2 / HIPAA reports
 * query in Step 6. If any of these fail, the compliance posture is broken.
 */

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()->inClinic($this->clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'pw123',
    ]);
    $this->token = $login->json('data.accessToken');
});

it('writes an audit_logs row with isPhiAccess=true when a patient is created via the API', function () {
    $beforeCount = AuditLog::where('isPhiAccess', true)->count();

    $this->withToken($this->token)->postJson('/api/patients', [
        'name' => 'Audited Patient',
        'emrId' => 'EMR-AUD-1',
    ])->assertStatus(201);

    $afterCount = AuditLog::where('isPhiAccess', true)->count();
    expect($afterCount)->toBeGreaterThan($beforeCount);

    $row = AuditLog::where('isPhiAccess', true)
        ->where('action', 'create')
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->userId)->toBe($this->user->id);
    expect($row->clinicId)->toBe($this->clinic->id);
    expect($row->entityType)->toBe('patient');
    expect($row->after['name'] ?? null)->toBe('Audited Patient');
});

it('writes a read audit_logs row when a patient show endpoint is hit', function () {
    $patient = Patient::factory()->inClinic($this->clinic)->create();

    $this->withToken($this->token)
        ->getJson("/api/patients/{$patient->id}")
        ->assertStatus(200);

    $row = AuditLog::where('isPhiAccess', true)
        ->where('action', 'read')
        ->where('entityId', $patient->id)
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->userId)->toBe($this->user->id);
    expect($row->entityType)->toBe('patient');
});

it('writes an update audit_logs row with before/after snapshot on patient patch', function () {
    $patient = Patient::factory()->inClinic($this->clinic)->create([
        'reportStatus' => 'needs_report',
    ]);

    $this->withToken($this->token)
        ->patchJson("/api/patients/{$patient->id}", ['reportStatus' => 'completed'])
        ->assertStatus(200);

    $row = AuditLog::where('isPhiAccess', true)
        ->where('action', 'update')
        ->where('entityId', $patient->id)
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->before['reportStatus'] ?? null)->toBe('needs_report');
    expect($row->after['reportStatus'] ?? null)->toBe('completed');
});

it('writes a delete audit_logs row when a patient is soft-deleted', function () {
    $full = User::factory()->inClinic($this->clinic)->create(['permission' => 'full', 'password' => \Illuminate\Support\Facades\Hash::make('pw123')]);
    $login = $this->postJson('/api/auth/login', ['email' => $full->email, 'password' => 'pw123']);
    $fullToken = $login->json('data.accessToken');

    $patient = Patient::factory()->inClinic($this->clinic)->create();

    $this->withToken($fullToken)
        ->deleteJson("/api/patients/{$patient->id}")
        ->assertStatus(204);

    $row = AuditLog::where('isPhiAccess', true)
        ->whereIn('action', ['delete', 'hard_delete'])
        ->where('entityId', $patient->id)
        ->latest('occurredAt')
        ->first();

    expect($row)->not->toBeNull();
});

it('every PHI audit row carries a requestId for correlation', function () {
    Patient::factory()->inClinic($this->clinic)->create(['name' => 'For Request ID Test']);

    $this->withToken($this->token)
        ->postJson('/api/patients', ['name' => 'Req-ID Patient', 'emrId' => 'EMR-REQ-1'])
        ->assertStatus(201);

    $row = AuditLog::where('isPhiAccess', true)
        ->where('action', 'create')
        ->latest('occurredAt')
        ->first();

    expect($row->requestId)->not->toBeEmpty();
});
