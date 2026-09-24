<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deployer-only audit reporting endpoints. Three lenses on the audit data:
 *
 *   - /audit/hipaa    — PHI-access-only rows (isPhiAccess=true), the core
 *                        HIPAA §164.312(b) audit trail. Filterable by date,
 *                        user, clinic, entityType.
 *   - /audit/soc2     — all audit_logs rows including auth events.
 *   - /audit/activity — system operational events from activity_logs
 *                        (AI runs, billing cycle, EMR syncs, queue failures).
 *
 * All three are gated by role=admin OR role=deployer at the route level.
 * Controllers apply a defensive policy check as well.
 */
class AuditController extends Controller
{
    public function hipaa(Request $request): JsonResponse
    {
        $this->assertDeployerOrAdmin($request);

        $query = AuditLog::query()->where('isPhiAccess', true);
        $this->applyFilters($query, $request);

        $page = $query->latest('occurredAt')->paginate((int) $request->integer('perPage', 50));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
            ],
        ]);
    }

    public function soc2(Request $request): JsonResponse
    {
        $this->assertDeployerOrAdmin($request);

        $query = AuditLog::query();
        $this->applyFilters($query, $request);

        $page = $query->latest('occurredAt')->paginate((int) $request->integer('perPage', 50));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
            ],
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $this->assertDeployerOrAdmin($request);

        $query = ActivityLog::query();

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('clinicId')) {
            $query->where('clinicId', $request->string('clinicId'));
        }

        if ($request->filled('from')) {
            $query->where('occurredAt', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->where('occurredAt', '<=', $request->string('to')->toString());
        }

        $page = $query->latest('occurredAt')->paginate((int) $request->integer('perPage', 50));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
            ],
        ]);
    }

    private function assertDeployerOrAdmin(Request $request): void
    {
        $role = $request->user()?->role;
        if (! in_array($role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            abort(403, 'This endpoint requires an admin or deployer role.');
        }
    }

    /**
     * Shared filter set for the hipaa + soc2 endpoints (both on audit_logs).
     */
    private function applyFilters(
        \Illuminate\Contracts\Database\Eloquent\Builder $query,
        Request $request,
    ): void {
        if ($request->filled('userId')) {
            $query->where('userId', $request->string('userId'));
        }

        if ($request->filled('clinicId')) {
            $query->where('clinicId', $request->string('clinicId'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('entityType')) {
            $query->where('entityType', $request->string('entityType'));
        }

        if ($request->filled('from')) {
            $query->where('occurredAt', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->where('occurredAt', '<=', $request->string('to')->toString());
        }
    }
}
