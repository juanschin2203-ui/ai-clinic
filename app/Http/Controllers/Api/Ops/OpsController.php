<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ops;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\RunAiPipeline;
use App\Models\ActivityLog;
use App\Models\MedicalCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Operator-only endpoints that sit under /api/ops. Visibility: admin + deployer.
 * These are the buttons behind the Deployer's audit / operations dashboard
 * (Force AI Re-process, queue depth chart, token usage report).
 *
 * Route-level middleware guards via `role:admin,deployer`; the controller
 * re-asserts defensively inside each method.
 */
class OpsController extends Controller
{
    /**
     * Queue depth — counts pending + in-progress per logical queue.
     * Reads directly from Redis list keys that Laravel's queue driver uses.
     * (queue:<name> for pending, queue:<name>:reserved for in-flight.)
     */
    public function queueDepth(Request $request): JsonResponse
    {
        $this->assertOperator($request);

        $queues = ['default', 'ai-pipeline', 'billing', 'notifications', 'maintenance'];
        $depths = [];

        foreach ($queues as $name) {
            try {
                $pending = (int) Redis::connection()->command('LLEN', ["queues:{$name}"]);
                $reserved = (int) Redis::connection()->command('ZCARD', ["queues:{$name}:reserved"]);
                $delayed = (int) Redis::connection()->command('ZCARD', ["queues:{$name}:delayed"]);
            } catch (\Throwable $e) {
                $pending = $reserved = $delayed = 0;
            }

            $depths[$name] = [
                'pending' => $pending,
                'reserved' => $reserved,
                'delayed' => $delayed,
                'total' => $pending + $reserved + $delayed,
            ];
        }

        return response()->json([
            'data' => $depths,
            'totalAcrossQueues' => array_sum(array_column($depths, 'total')),
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    /**
     * Anthropic token consumption, grouped by clinic, for an optional date range.
     * Reads from activity_logs rows emitted by the pipeline orchestrator.
     */
    public function tokenUsage(Request $request): JsonResponse
    {
        $this->assertOperator($request);

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())
            : CarbonImmutable::now();

        // SQLite doesn't support jsonb → we extract via the `context` JSON column
        // in Laravel's portable form, then sum in PHP. Small enough log volume
        // at current scale; swap to a SQL materialized view later if needed.
        $rows = ActivityLog::query()
            ->where('category', 'ai')
            ->whereBetween('occurredAt', [$from, $to])
            ->get(['clinicId', 'context']);

        $perClinic = [];
        foreach ($rows as $row) {
            $tokens = (int) ($row->context['tokens'] ?? 0);
            if ($tokens === 0) {
                continue;
            }
            $cid = $row->clinicId ?? 'null';
            $perClinic[$cid] = ($perClinic[$cid] ?? 0) + $tokens;
        }

        return response()->json([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'totalTokens' => array_sum($perClinic),
            'perClinic' => $perClinic,
        ]);
    }

    /**
     * Recent errors from the activity log (severity=error).
     */
    public function errors(Request $request): JsonResponse
    {
        $this->assertOperator($request);

        $since = $request->filled('since')
            ? CarbonImmutable::parse($request->string('since')->toString())
            : CarbonImmutable::now()->subDay();

        $rows = ActivityLog::query()
            ->where('severity', 'error')
            ->where('occurredAt', '>=', $since)
            ->latest('occurredAt')
            ->limit((int) $request->integer('limit', 100))
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Force re-run the AI pipeline for a specific case — the Deployer's
     * "Force AI Re-process" button. Queues fresh, does NOT wait.
     */
    public function reprocessCase(Request $request, string $caseId): JsonResponse
    {
        $this->assertOperator($request);

        $case = MedicalCase::withoutGlobalScopes()->find($caseId);
        if ($case === null) {
            return response()->json([
                'error' => [
                    'code' => 'case_not_found',
                    'message' => 'No case with that id.',
                ],
            ], 404);
        }

        // Reset AI-owned fields so the pipeline starts from a clean slate.
        $case->forceFill([
            'status' => \App\Enums\CaseStatus::Processing->value,
            'suggestedCPT' => null,
            'suggestedDX' => null,
            'modifiers' => null,
            'stateCompliance' => null,
            'aiTokensUsed' => 0,
            'aiCompletedAt' => null,
        ])->save();

        RunAiPipeline::dispatch($case->id);

        return response()->json([
            'message' => 'Pipeline re-dispatched.',
            'caseId' => $case->id,
        ]);
    }

    private function assertOperator(Request $request): void
    {
        $role = $request->user()?->role;
        if (! in_array($role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            abort(403, 'Ops endpoints require admin or deployer role.');
        }
    }
}
