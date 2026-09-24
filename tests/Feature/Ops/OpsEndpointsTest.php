<?php

declare(strict_types=1);

use App\Jobs\RunAiPipeline;
use App\Models\ActivityLog;
use App\Models\Clinic;
use App\Models\MedicalCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->clinicUser = User::factory()->inClinic($this->clinic)->withPassword('pw123')->create();
    $this->admin = User::factory()->admin()->withPassword('pw123')->create();
    $this->deployer = User::factory()->deployer()->withPassword('pw123')->create();
});

function tokenFor(\App\Models\User $user): string
{
    $res = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'pw123',
    ]);

    return $res->json('data.accessToken');
}

it('/api/ops/queue/depth returns per-queue depths for admin', function () {
    $token = tokenFor($this->admin);

    $response = $this->withToken($token)->getJson('/api/ops/queue/depth');

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'default' => ['pending', 'reserved', 'delayed', 'total'],
                'ai-pipeline' => ['pending', 'reserved', 'delayed', 'total'],
                'billing' => ['pending', 'reserved', 'delayed', 'total'],
            ],
            'totalAcrossQueues',
            'timestamp',
        ]);
});

it('/api/ops/queue/depth is rejected for clinic users (403)', function () {
    $token = tokenFor($this->clinicUser);

    $this->withToken($token)->getJson('/api/ops/queue/depth')->assertStatus(403);
});

it('/api/ops/ai/token-usage aggregates per-clinic token spend from activity_logs', function () {
    ActivityLog::query()->create([
        'clinicId' => $this->clinic->id,
        'category' => 'ai',
        'event' => 'ai.pipeline.stage_completed.suggestCpt',
        'severity' => 'info',
        'message' => 'stage done',
        'context' => ['tokens' => 1500, 'caseId' => 'x'],
        'occurredAt' => CarbonImmutable::now(),
    ]);
    ActivityLog::query()->create([
        'clinicId' => $this->clinic->id,
        'category' => 'ai',
        'event' => 'ai.pipeline.stage_completed.mapDiagnoses',
        'severity' => 'info',
        'message' => 'stage done',
        'context' => ['tokens' => 800, 'caseId' => 'x'],
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = tokenFor($this->deployer);

    $response = $this->withToken($token)->getJson('/api/ops/ai/token-usage');

    $response
        ->assertStatus(200)
        ->assertJsonPath("perClinic.{$this->clinic->id}", 2300)
        ->assertJsonPath('totalTokens', 2300);
});

it('/api/ops/cases/{case}/reprocess dispatches a fresh RunAiPipeline job', function () {
    Bus::fake();

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-OPS-1',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'completed',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'suggestedCPT' => [['code' => '99214', 'citation' => 'x', 'confidence' => 0.9]],
        'aiTokensUsed' => 5000,
    ]);

    $token = tokenFor($this->admin);

    $this->withToken($token)
        ->postJson("/api/ops/cases/{$case->id}/reprocess")
        ->assertStatus(200)
        ->assertJsonPath('caseId', $case->id);

    Bus::assertDispatched(RunAiPipeline::class, fn ($job) => $job->caseId === $case->id);

    // Case state was reset:
    $case->refresh();
    expect($case->status->value)->toBe('processing');
    expect($case->suggestedCPT)->toBeNull();
    expect($case->aiTokensUsed)->toBe(0);
});

it('/api/ops/cases/{id}/reprocess returns 404 for an unknown case id', function () {
    $token = tokenFor($this->admin);

    $fakeId = (string) \Illuminate\Support\Str::uuid();

    $this->withToken($token)
        ->postJson("/api/ops/cases/{$fakeId}/reprocess")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'case_not_found');
});

it('/api/ops/errors returns recent error-severity activity entries', function () {
    ActivityLog::query()->create([
        'clinicId' => $this->clinic->id,
        'category' => 'ai',
        'event' => 'ai.pipeline.stage_failed.suggestCpt',
        'severity' => 'error',
        'message' => 'parse failed after 3 retries',
        'occurredAt' => CarbonImmutable::now(),
    ]);

    // Non-error row — should not appear in the response.
    ActivityLog::query()->create([
        'clinicId' => $this->clinic->id,
        'category' => 'ai',
        'event' => 'ai.pipeline.stage_completed.suggestCpt',
        'severity' => 'info',
        'message' => 'stage done',
        'occurredAt' => CarbonImmutable::now(),
    ]);

    $token = tokenFor($this->deployer);

    $response = $this->withToken($token)->getJson('/api/ops/errors');

    $response->assertStatus(200);
    $events = collect($response->json('data'))->pluck('event');
    expect($events)->toContain('ai.pipeline.stage_failed.suggestCpt');
    expect($events)->not->toContain('ai.pipeline.stage_completed.suggestCpt');
});

it('/api/ops/* endpoints are rejected for unauthenticated callers (401)', function () {
    $this->getJson('/api/ops/queue/depth')->assertStatus(401);
    $this->getJson('/api/ops/ai/token-usage')->assertStatus(401);
    $this->getJson('/api/ops/errors')->assertStatus(401);
});
