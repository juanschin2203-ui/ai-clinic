<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\CaseStatus;
use App\Events\Ai\AiPipelineCompleted;
use App\Events\Ai\AiPipelineFailed;
use App\Events\Ai\AiPipelineStarted;
use App\Models\ActivityLog;
use App\Models\MedicalCase;
use App\Services\Ai\Stages\AbstractStage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

/**
 * Coordinates the 6-stage AI pipeline for a single MedicalCase.
 *
 *   1. Emit AiPipelineStarted, set case status = Processing
 *   2. For each stage (in order from config('anthropic.pipeline_stages')):
 *        a. Check token budget — abort if already at cap
 *        b. Run stage (one shot — the AnthropicClient handles per-call
 *           retries internally; orchestrator does NOT retry a stage here)
 *        c. On success: add tokens to running total, append to audit trail
 *        d. On failure: move case to needsReview, emit AiPipelineFailed,
 *           halt (remaining stages do NOT run)
 *   3. If all 6 succeed: emit AiPipelineCompleted, case → PendingDiagnosisApproval
 *      (so a coder reviews AI output before delivery)
 *
 * Per-stage internal retry (3× with backoff) lives in AnthropicClient,
 * NOT here. If the client exhausts retries, it throws; AbstractStage::guard
 * converts that into a StageResult::fail which the orchestrator handles.
 */
class PipelineOrchestrator
{
    public function run(MedicalCase $case): void
    {
        $case->refresh();

        Event::dispatch(new AiPipelineStarted(
            caseId: $case->id,
            clinicId: $case->clinic,
        ));

        $this->setStatus($case, CaseStatus::Processing);
        $this->activity($case, 'ai.pipeline.started', 'info', 'Pipeline started.');

        $stageClasses = (array) config('anthropic.pipeline_stages');
        $tokenBudget = (int) config('anthropic.max_tokens_per_case', 50000);

        $perStage = [];
        $overallConfidence = 1.0;
        $tokensUsed = (int) $case->aiTokensUsed;

        foreach ($stageClasses as $stageClass) {
            /** @var AbstractStage $stage */
            $stage = app($stageClass);
            $stageName = $stage->name();

            // Token-budget gate — defends against runaway cost per case.
            if ($tokensUsed >= $tokenBudget) {
                $this->activity($case, 'ai.pipeline.budget_exceeded', 'error',
                    "Aborting before {$stageName}: token budget {$tokenBudget} reached.");
                $this->moveToNeedsReview($case, $stageName, 'token_budget_exceeded',
                    "AI token budget of {$tokenBudget} exceeded.", $tokensUsed);

                return;
            }

            $result = $stage->run($case);
            $perStage[$stageName] = [
                'tokens' => $result->tokensUsed,
                'confidence' => $result->confidence,
            ];

            if (! $result->succeeded) {
                $this->activity($case, "ai.pipeline.stage_failed.{$stageName}", 'error',
                    "Stage {$stageName} failed: {$result->errorMessage}");

                $this->moveToNeedsReview(
                    case: $case,
                    failedStage: $stageName,
                    errorCode: $result->errorCode ?? 'unknown',
                    errorMessage: $result->errorMessage ?? 'Unknown error',
                    tokensUsed: $tokensUsed + $result->tokensUsed,
                );

                return;
            }

            $tokensUsed += $result->tokensUsed;
            if ($result->confidence !== null && $result->confidence < $overallConfidence) {
                $overallConfidence = $result->confidence;
            }

            $this->activity($case, "ai.pipeline.stage_completed.{$stageName}", 'info',
                "Stage {$stageName} completed.", [
                    'tokens' => $result->tokensUsed,
                    'confidence' => $result->confidence,
                ]);

            // Persist running token count + fresh reload for next stage.
            $case->forceFill(['aiTokensUsed' => $tokensUsed])->save();
            $case->refresh();
        }

        // All stages green → case is ready for coder review.
        $case->forceFill([
            'status' => CaseStatus::PendingDiagnosisApproval->value,
            'aiCompletedAt' => CarbonImmutable::now(),
        ])->save();

        $this->activity($case, 'ai.pipeline.completed', 'info',
            "Pipeline complete. Case ready for coder review.", [
                'tokensUsed' => $tokensUsed,
                'overallConfidence' => $overallConfidence,
            ]);

        Event::dispatch(new AiPipelineCompleted(
            caseId: $case->id,
            clinicId: $case->clinic,
            tokensUsed: $tokensUsed,
            overallConfidence: $overallConfidence,
            perStage: $perStage,
        ));
    }

    private function setStatus(MedicalCase $case, CaseStatus $status): void
    {
        $case->forceFill(['status' => $status->value])->save();
    }

    private function moveToNeedsReview(
        MedicalCase $case,
        string $failedStage,
        string $errorCode,
        string $errorMessage,
        int $tokensUsed,
    ): void {
        $audit = $case->audit ?? [];
        $audit[] = [
            'action' => 'AI Pipeline Failed',
            'detail' => "Stage {$failedStage}: {$errorMessage}",
            'ts' => CarbonImmutable::now()->toDateString(),
        ];

        $case->forceFill([
            'status' => CaseStatus::NeedsReview->value,
            'aiTokensUsed' => $tokensUsed,
            'audit' => $audit,
        ])->save();

        Event::dispatch(new AiPipelineFailed(
            caseId: $case->id,
            clinicId: $case->clinic,
            failedStageName: $failedStage,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            tokensUsed: $tokensUsed,
        ));
    }

    private function activity(
        MedicalCase $case,
        string $event,
        string $severity,
        string $message,
        array $context = [],
    ): void {
        ActivityLog::query()->create([
            'userId' => null,                     // pipeline runs in queue worker — no HTTP user
            'clinicId' => $case->clinic,
            'category' => 'ai',
            'event' => $event,
            'severity' => $severity,
            'message' => $message,
            'context' => $context + ['caseId' => $case->id, 'caseNum' => $case->num],
            'occurredAt' => CarbonImmutable::now(),
        ]);
    }
}
