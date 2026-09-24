<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Models\Clinic;
use App\Models\MedicalCase;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create(['name' => 'Test Clinic']);
    $this->coder = User::factory()->inClinic($this->clinic)->coder()->withPassword('pw123')->create();

    $this->case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-TEST-1',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test Patient',
        'state' => 'CA',
        'status' => CaseStatus::NeedsReview->value,
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 400.00,
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->coder->email,
        'password' => 'pw123',
    ]);
    $this->token = $login->json('data.accessToken');
});

it('lists cases scoped to the coder\'s clinic', function () {
    // Another clinic's case should be invisible.
    $otherClinic = Clinic::factory()->create();
    MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-OTHER-1',
        'clinic' => $otherClinic->id,
        'clinicName' => $otherClinic->name,
        'patient' => 'Other',
        'state' => 'TX',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
    ]);

    $response = $this->withToken($this->token)->getJson('/api/cases');

    $response->assertStatus(200);
    $nums = collect($response->json('data'))->pluck('num')->all();
    expect($nums)->toContain('SNP-TEST-1');
    expect($nums)->not->toContain('SNP-OTHER-1');
});

it('shows a case in full JSX shape', function () {
    $response = $this->withToken($this->token)->getJson("/api/cases/{$this->case->id}");

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'num', 'patient', 'dob', 'dos', 'gender', 'claimNum',
                'emrId', 'status', 'statusKey', 'svcs', 'total', 'clinic',
                'clinicName', 'state', 'reportType', 'inputSource', 'notes',
                'suggestedCPT', 'suggestedDX', 'modifiers', 'stateCompliance',
                'emails', 'audit', 'timeline', 'aiTokensUsed', 'aiTokenBudget',
            ],
        ])
        ->assertJsonPath('data.num', 'SNP-TEST-1')
        ->assertJsonPath('data.status', 'Needs Review')
        ->assertJsonPath('data.statusKey', 'needsReview');
});

it('rejects show for another clinic\'s case with 403 or 404', function () {
    $otherClinic = Clinic::factory()->create();
    $otherCase = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-OTHER-2',
        'clinic' => $otherClinic->id,
        'clinicName' => $otherClinic->name,
        'patient' => 'Other',
        'state' => 'TX',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
    ]);

    $response = $this->withToken($this->token)->getJson("/api/cases/{$otherCase->id}");

    expect($response->status())->toBeIn([403, 404]);
});

it('creates a case with auto-generated num and initial audit trail', function () {
    $response = $this->withToken($this->token)->postJson('/api/cases', [
        'clinic' => $this->clinic->id,
        'patient' => 'New Patient',
        'state' => 'CA',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 275,
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['num'])->toStartWith('SNP-');
    expect($data['audit'])->toBeArray();
    expect($data['audit'][0]['action'] ?? null)->toBe('Created');
    expect($data['timeline'][0]['label'] ?? null)->toBe('Created');
    expect($data['assignedCoderId'])->toBe($this->coder->id);
});

it('updates a case via PATCH', function () {
    $response = $this->withToken($this->token)->patchJson("/api/cases/{$this->case->id}", [
        'notes' => 'Updated clinical notes',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.notes', 'Updated clinical notes');
});

it('approves a case, transitioning it to completed and appending audit', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/cases/{$this->case->id}/approve");

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'Completed')
        ->assertJsonPath('data.statusKey', 'completed');

    $audit = $response->json('data.audit');
    expect(collect($audit)->pluck('action'))->toContain('Approved');

    $timeline = $response->json('data.timeline');
    expect(collect($timeline)->pluck('label'))->toContain('Completed');
});

it('sends a case back to Processing with a reason', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/cases/{$this->case->id}/send-back", [
            'reason' => 'CPT code 99215 requires E/M MDM justification',
        ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.statusKey', 'processing');

    $audit = $response->json('data.audit');
    expect(collect($audit)->pluck('action'))->toContain('Sent Back');
});

it('rejects send-back without a reason (422)', function () {
    $this->withToken($this->token)
        ->postJson("/api/cases/{$this->case->id}/send-back", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

it('rejects send-back by a viewer-permission user with 403', function () {
    $viewer = User::factory()->inClinic($this->clinic)->viewer()->withPassword('pw123')->create();
    $viewerLogin = $this->postJson('/api/auth/login', [
        'email' => $viewer->email,
        'password' => 'pw123',
    ]);
    $viewerToken = $viewerLogin->json('data.accessToken');

    $this->withToken($viewerToken)
        ->postJson("/api/cases/{$this->case->id}/send-back", ['reason' => 'test'])
        ->assertStatus(403);
});

it('reruns AI, clearing prior suggestions and returning to Processing', function () {
    $this->case->forceFill([
        'status' => CaseStatus::Completed->value,
        'suggestedCPT' => [['code' => '99213', 'desc' => 'test']],
        'aiTokensUsed' => 2000,
    ])->save();

    // Only `full` permission can rerun AI (per policy).
    $full = User::factory()->inClinic($this->clinic)->create(['permission' => 'full', 'password' => \Illuminate\Support\Facades\Hash::make('pw123')]);
    $fullLogin = $this->postJson('/api/auth/login', [
        'email' => $full->email,
        'password' => 'pw123',
    ]);
    $fullToken = $fullLogin->json('data.accessToken');

    $response = $this->withToken($fullToken)
        ->postJson("/api/cases/{$this->case->id}/rerun-ai");

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.statusKey', 'processing')
        ->assertJsonPath('data.suggestedCPT', [])
        ->assertJsonPath('data.aiTokensUsed', 0);
});

it('soft-deletes a case (full permission required)', function () {
    $full = User::factory()->inClinic($this->clinic)->create(['permission' => 'full', 'password' => \Illuminate\Support\Facades\Hash::make('pw123')]);
    $fullLogin = $this->postJson('/api/auth/login', [
        'email' => $full->email,
        'password' => 'pw123',
    ]);
    $fullToken = $fullLogin->json('data.accessToken');

    $this->withToken($fullToken)
        ->deleteJson("/api/cases/{$this->case->id}")
        ->assertStatus(204);

    // Raw DB still has the row (soft-delete sets deletedAt).
    $raw = \Illuminate\Support\Facades\DB::table('cases')->where('id', $this->case->id)->first();
    expect($raw)->not->toBeNull();
    expect($raw->deletedAt)->not->toBeNull();

    // Normal Eloquent queries skip it (SoftDeletes scope).
    expect(MedicalCase::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->find($this->case->id))->toBeNull();

    // withTrashed includes it.
    expect(
        MedicalCase::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->withTrashed()
            ->find($this->case->id)
    )->not->toBeNull();
});
