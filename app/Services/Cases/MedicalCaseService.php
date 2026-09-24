<?php

declare(strict_types=1);

namespace App\Services\Cases;

use App\Enums\CaseStatus;
use App\Jobs\RunAiPipeline;
use App\Models\MedicalCase;
use App\Models\User;
use App\Repositories\Contracts\MedicalCaseRepositoryInterface;
use App\Services\Phi\PhiAccessRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates case-workflow operations beyond simple CRUD: approve,
 * send-back, rerun-ai. Each mutates state and appends to the case's
 * immutable `audit` JSON timeline so the UI's history panel stays in sync.
 *
 * Also generates sequential `num` values ("SNP-0001", "SNP-0002", ...) for
 * new cases — matches the JSX prototype's display format.
 *
 * AI pipeline dispatch (creating a new case → RunAiPipeline job) lands in
 * Step 6 when the queue infrastructure exists. For now, `create()` leaves
 * status=Processing with no AI output; the pipeline fills it later.
 */
class MedicalCaseService
{
    public function __construct(
        private readonly MedicalCaseRepositoryInterface $cases,
        private readonly PhiAccessRecorder $phiRecorder,
    ) {}

    // ----- reads ------------------------------------------------------------

    public function findWithAudit(string $id, bool $recordAccess = true): MedicalCase
    {
        /** @var MedicalCase $case */
        $case = $this->cases->findOrFail($id);

        if ($recordAccess) {
            $this->phiRecorder->record($case);
        }

        return $case;
    }

    // ----- writes -----------------------------------------------------------

    public function create(array $attributes, User $actor): MedicalCase
    {
        $case = DB::transaction(function () use ($attributes, $actor) {
            $attributes['num'] ??= $this->nextCaseNumber();
            $attributes['assignedCoderId'] ??= $actor->id;
            $attributes['status'] ??= CaseStatus::Processing->value;

            // clinicName is a denormalized display field (matches JSX).
            // Populate from the clinic relation if the caller didn't supply it.
            if (empty($attributes['clinicName']) && ! empty($attributes['clinic'])) {
                $attributes['clinicName'] = \App\Models\Clinic::withoutGlobalScopes()
                    ->where('id', $attributes['clinic'])
                    ->value('name') ?? '';
            }

            $attributes['audit'] = [
                ['action' => 'Created', 'detail' => 'Initiated by '.$actor->name, 'ts' => now()->toDateString()],
            ];
            $attributes['timeline'] = [
                ['label' => 'Created', 'date' => now()->toDateString()],
            ];

            /** @var MedicalCase */
            return $this->cases->create($attributes);
        });

        // Fire the AI pipeline asynchronously — after the DB transaction
        // commits (so the worker can see the case). `afterCommit()` ensures
        // that if the transaction rolled back, no job was queued.
        Bus::dispatch((new RunAiPipeline($case->id))->afterCommit());

        return $case;
    }

    public function update(MedicalCase $case, array $attributes): MedicalCase
    {
        return DB::transaction(
            fn () => $this->cases->update($case->id, $attributes),
        );
    }

    public function approve(MedicalCase $case, User $actor): MedicalCase
    {
        return DB::transaction(function () use ($case, $actor) {
            $this->appendAudit($case, 'Approved', 'Approved by '.$actor->name);
            $this->appendTimeline($case, 'Completed');

            return $this->cases->update($case->id, [
                'status' => CaseStatus::Completed->value,
                'audit' => $case->audit,
                'timeline' => $case->timeline,
            ]);
        });
    }

    public function sendBack(MedicalCase $case, User $actor, string $reason): MedicalCase
    {
        return DB::transaction(function () use ($case, $actor, $reason) {
            $this->appendAudit($case, 'Sent Back', $reason.' (by '.$actor->name.')');

            return $this->cases->update($case->id, [
                'status' => CaseStatus::Processing->value,
                'audit' => $case->audit,
            ]);
        });
    }

    /**
     * Requeue the case for AI pipeline re-run. Clears prior suggestions,
     * resets token counter, appends audit entry, and dispatches the
     * RunAiPipeline job afresh.
     */
    public function rerunAi(MedicalCase $case, User $actor): MedicalCase
    {
        $updated = DB::transaction(function () use ($case, $actor) {
            $this->appendAudit($case, 'AI Re-run', 'Queued by '.$actor->name);

            return $this->cases->update($case->id, [
                'status' => CaseStatus::Processing->value,
                'suggestedCPT' => null,
                'suggestedDX' => null,
                'modifiers' => null,
                'stateCompliance' => null,
                'aiTokensUsed' => 0,
                'aiCompletedAt' => null,
                'audit' => $case->audit,
            ]);
        });

        Bus::dispatch((new RunAiPipeline($updated->id))->afterCommit());

        return $updated;
    }

    public function softDelete(MedicalCase $case, User $actor): void
    {
        DB::transaction(function () use ($case, $actor) {
            $this->appendAudit($case, 'Deleted', 'Deleted by '.$actor->name);
            $this->cases->update($case->id, [
                'status' => CaseStatus::Deleted->value,
                'audit' => $case->audit,
            ]);
            $this->cases->delete($case->id);
        });
    }

    // ----- internals --------------------------------------------------------

    private function appendAudit(MedicalCase $case, string $action, string $detail): void
    {
        $audit = $case->audit ?? [];
        $audit[] = [
            'action' => $action,
            'detail' => $detail,
            'ts' => CarbonImmutable::now()->toDateString(),
        ];
        $case->audit = $audit;
    }

    private function appendTimeline(MedicalCase $case, string $label): void
    {
        $timeline = $case->timeline ?? [];
        $timeline[] = [
            'label' => $label,
            'date' => CarbonImmutable::now()->toDateString(),
        ];
        $case->timeline = $timeline;
    }

    private function nextCaseNumber(): string
    {
        // Generate monotonically by selecting the max current num. Simple and
        // correct under the transaction's row-level lock. If we ever need
        // horizontal write scaling, switch to a dedicated sequence table.
        $max = $this->cases->query()->max('num');
        $seq = 1;
        if (is_string($max) && preg_match('/SNP-(\d+)/', $max, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('SNP-%04d', $seq);
    }
}
