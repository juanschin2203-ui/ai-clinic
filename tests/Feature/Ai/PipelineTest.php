<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Events\Ai\AiPipelineCompleted;
use App\Events\Ai\AiPipelineFailed;
use App\Jobs\RunAiPipeline;
use App\Models\ActivityLog;
use App\Models\Clinic;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\Anthropic\AnthropicClientInterface;
use App\Services\Anthropic\StructuredOutputException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeAnthropicClient;

beforeEach(function () {
    $this->fake = new FakeAnthropicClient();
    $this->app->instance(AnthropicClientInterface::class, $this->fake);

    $this->clinic = Clinic::factory()->create(['name' => 'AI Test Clinic']);
    $this->user = User::factory()->inClinic($this->clinic)->withPassword('pw123')->create();

    $this->notes = "HISTORY OF PRESENT ILLNESS:\nTest patient, 40yo warehouse worker, back strain from lifting 60lb box on 2026-03-10.\n\nEXAM:\nTenderness L4-L5. Positive SLR left at 45 degrees.\n\nASSESSMENT:\nLumbar radiculopathy left (M54.41).\n\nPLAN: PT 2x/week, meloxicam.";
});

/** Script all six stages with happy-path responses. */
function stubAllStagesHappy(FakeAnthropicClient $fake, string $citation): void
{
    $fake->forStage('extractPatientData', [
        'patientName' => 'Test Patient',
        'dob' => '1985-03-12',
        'gender' => 'Male',
        'dateOfService' => '2026-03-15',
        'claimNumber' => 'WC-2026-99999',
        'employer' => 'Test Co',
        'provider' => 'Dr. Test',
        'dateOfInjury' => '2026-03-10',
        'confidence' => 0.92,
    ]);

    $fake->forStage('draftReport', [
        'sections' => [
            ['heading' => 'HPI', 'body' => 'Patient presents with back pain after lifting injury.'],
            ['heading' => 'Plan', 'body' => 'PT referral, meloxicam 15mg.'],
        ],
        'confidence' => 0.88,
    ]);

    $fake->forStage('suggestCpt', [
        'codes' => [
            [
                'code' => '99214',
                'desc' => 'Office Visit, Est, Lvl 4',
                'reasoning' => 'Moderate-complexity MDM supported by exam findings.',
                'citation' => $citation,
                'confidence' => 0.85,
            ],
        ],
        'overallConfidence' => 0.85,
    ]);

    $fake->forStage('applyModifiers', [
        'modifiers' => [],
        'confidence' => 1.0,
    ]);

    $fake->forStage('mapDiagnoses', [
        'diagnoses' => [
            [
                'code' => 'M54.41',
                'desc' => 'Lumbago with sciatica, right side',
                'primary' => true,
                'citation' => $citation,
                'confidence' => 0.90,
            ],
        ],
        'overallConfidence' => 0.90,
    ]);

    $fake->forStage('checkCompliance', [
        'compliant' => true,
        'system' => 'CA OMFS',
        'note' => 'All codes compliant with OMFS fee schedule.',
        'violations' => [],
        'confidence' => 0.95,
    ]);
}

it('runs the pipeline end-to-end and lands on PendingDiagnosisApproval', function () {
    Event::fake([AiPipelineCompleted::class, AiPipelineFailed::class]);

    $citation = 'Tenderness L4-L5. Positive SLR left at 45 degrees.';
    stubAllStagesHappy($this->fake, $citation);

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-AI-1',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test Patient',
        'state' => 'CA',
        'status' => CaseStatus::Processing->value,
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'notes' => $this->notes,
    ]);

    $orchestrator = app(\App\Services\Ai\PipelineOrchestrator::class);
    $orchestrator->run($case);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::PendingDiagnosisApproval);
    expect($case->suggestedCPT)->not->toBeEmpty();
    expect($case->suggestedCPT[0]['code'])->toBe('99214');
    expect($case->suggestedCPT[0]['citation'])->toBe($citation);
    expect($case->suggestedDX[0]['code'])->toBe('M54.41');
    expect($case->suggestedDX[0]['primary'])->toBeTrue();
    expect($case->stateCompliance['compliant'])->toBeTrue();
    expect($case->aiTokensUsed)->toBeGreaterThan(0);
    expect($case->aiCompletedAt)->not->toBeNull();

    Event::assertDispatched(AiPipelineCompleted::class);
    Event::assertNotDispatched(AiPipelineFailed::class);

    expect($this->fake->calledStages())->toBe([
        'extractPatientData', 'draftReport', 'suggestCpt',
        'applyModifiers', 'mapDiagnoses', 'checkCompliance',
    ]);
});

it('rejects CPT codes whose citations do not appear in the notes', function () {
    $this->fake->forStage('extractPatientData', ['confidence' => 0.9]);
    $this->fake->forStage('draftReport', ['sections' => [], 'confidence' => 0.9]);
    $this->fake->forStage('suggestCpt', [
        'codes' => [
            // VALID: citation is in the notes
            [
                'code' => '99214',
                'desc' => 'Office Visit',
                'reasoning' => '...',
                'citation' => 'Tenderness L4-L5. Positive SLR left at 45 degrees.',
                'confidence' => 0.9,
            ],
            // INVALID: hallucinated citation (not in notes)
            [
                'code' => '99999',
                'desc' => 'Fabricated code',
                'reasoning' => '...',
                'citation' => 'Patient has a fever of 104F and rash on legs.',  // NOT in notes
                'confidence' => 0.95,
            ],
        ],
    ]);
    $this->fake->forStage('applyModifiers', ['modifiers' => [], 'confidence' => 1.0]);
    $this->fake->forStage('mapDiagnoses', ['diagnoses' => [], 'overallConfidence' => 0]);
    $this->fake->forStage('checkCompliance', ['compliant' => true, 'system' => 'CA OMFS', 'note' => '', 'violations' => []]);

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-AI-2',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'notes' => $this->notes,
    ]);

    app(\App\Services\Ai\PipelineOrchestrator::class)->run($case);

    $case->refresh();
    expect($case->suggestedCPT)->toHaveCount(1);
    expect($case->suggestedCPT[0]['code'])->toBe('99214');
    // The hallucinated 99999 was filtered out.
    expect(collect($case->suggestedCPT)->pluck('code')->all())->not->toContain('99999');
});

it('moves a case to needsReview when a stage fails 3 times', function () {
    Event::fake([AiPipelineFailed::class]);

    $this->fake->forStage('extractPatientData', ['confidence' => 0.9]);
    $this->fake->forStage('draftReport', ['sections' => [], 'confidence' => 0.9]);
    $this->fake->throwStructuredOutput('suggestCpt', '{ malformed');

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-AI-3',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'notes' => $this->notes,
    ]);

    app(\App\Services\Ai\PipelineOrchestrator::class)->run($case);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::NeedsReview);

    $auditActions = collect($case->audit)->pluck('action');
    expect($auditActions)->toContain('AI Pipeline Failed');

    Event::assertDispatched(AiPipelineFailed::class, function ($event) {
        return $event->failedStageName === 'suggestCpt'
            && $event->errorCode === 'parse_failed';
    });

    // Remaining stages did NOT run
    expect($this->fake->calledStages())->not->toContain('applyModifiers');
    expect($this->fake->calledStages())->not->toContain('mapDiagnoses');
});

it('halts the pipeline when the token budget is exceeded', function () {
    stubAllStagesHappy($this->fake, 'Tenderness L4-L5. Positive SLR left at 45 degrees.');

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-AI-4',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'notes' => $this->notes,
        'aiTokensUsed' => 60000,     // Already over the 50k budget
        'aiTokenBudget' => 50000,
    ]);

    app(\App\Services\Ai\PipelineOrchestrator::class)->run($case);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::NeedsReview);
    // No stages should have run
    expect($this->fake->calledStages())->toHaveCount(0);
});

it('dispatches RunAiPipeline job when a case is created via the API', function () {
    Bus::fake();

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'pw123',
    ]);
    $token = $login->json('data.accessToken');

    $response = $this->withToken($token)->postJson('/api/cases', [
        'clinic' => $this->clinic->id,
        'patient' => 'Pipeline Dispatch Test',
        'state' => 'CA',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'notes' => $this->notes,
    ]);

    $response->assertStatus(201);

    Bus::assertDispatched(RunAiPipeline::class, function ($job) use ($response) {
        return $job->caseId === $response->json('data.id');
    });
});

it('writes activity_logs entries per stage completion', function () {
    stubAllStagesHappy($this->fake, 'Tenderness L4-L5. Positive SLR left at 45 degrees.');

    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-AI-5',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
        'notes' => $this->notes,
    ]);

    app(\App\Services\Ai\PipelineOrchestrator::class)->run($case);

    $activityEvents = ActivityLog::where('category', 'ai')->pluck('event');
    expect($activityEvents)->toContain('ai.pipeline.started');
    expect($activityEvents)->toContain('ai.pipeline.stage_completed.suggestCpt');
    expect($activityEvents)->toContain('ai.pipeline.completed');
});
