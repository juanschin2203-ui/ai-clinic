<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MedicalCase;
use App\Services\Ai\PipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from MedicalCaseService::create after a case is persisted.
 * Pulls from the `ai-pipeline` Redis queue, runs the 6-stage orchestrator.
 *
 *   - $tries = 1 at the job level: the AnthropicClient retries each API
 *     call internally (3x w/ backoff). If the orchestrator's
 *     moveToNeedsReview() fires, that IS the escalation — retrying the
 *     whole job would re-run successful early stages and waste tokens.
 *   - Timeout 15 minutes for a full 6-stage run (each stage 60s * 6 + overhead).
 */
class RunAiPipeline implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;      // 15 minutes — full pipeline

    public function __construct(
        public readonly string $caseId,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function handle(PipelineOrchestrator $orchestrator): void
    {
        $case = MedicalCase::withoutGlobalScopes()->find($this->caseId);
        if ($case === null) {
            // Case was deleted between dispatch and worker pick-up. Quiet no-op.
            return;
        }

        $orchestrator->run($case);
    }

    /**
     * Called by Laravel when the job throws. We don't attempt retries — the
     * orchestrator already moved the case to needsReview or we had an
     * unexpected infra error. The failure is logged via failed_jobs table.
     */
    public function failed(\Throwable $exception): void
    {
        // Final-line defense: make sure the case is marked as needing human
        // review in case the orchestrator threw without reaching its own
        // moveToNeedsReview() logic.
        $case = MedicalCase::withoutGlobalScopes()->find($this->caseId);
        if ($case === null) {
            return;
        }

        if ($case->status->value === 'processing') {
            $case->forceFill([
                'status' => 'needsReview',
                'audit' => array_merge($case->audit ?? [], [[
                    'action' => 'AI Pipeline Crashed',
                    'detail' => 'Unhandled exception: '.$exception->getMessage(),
                    'ts' => now()->toDateString(),
                ]]),
            ])->save();
        }
    }
}
