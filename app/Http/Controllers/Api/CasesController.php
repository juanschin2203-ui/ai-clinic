<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\SendBackCaseRequest;
use App\Http\Requests\Cases\StoreCaseRequest;
use App\Http\Requests\Cases\UpdateCaseRequest;
use App\Http\Resources\MedicalCaseResource;
use App\Models\MedicalCase;
use App\Repositories\Contracts\MedicalCaseRepositoryInterface;
use App\Services\Cases\MedicalCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CasesController extends Controller
{
    public function __construct(
        private readonly MedicalCaseRepositoryInterface $cases,
        private readonly MedicalCaseService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicalCase::class);

        $filters = $request->only([
            'status', 'tab', 'assignedCoderId', 'reportType', 'search',
        ]);

        $paginator = $this->cases->paginateWithFilters($filters, (int) $request->integer('perPage', 25));

        return MedicalCaseResource::collection($paginator)->response();
    }

    public function show(MedicalCase $case): JsonResponse
    {
        $this->authorize('view', $case);

        // findWithAudit() records the PHI access and returns a fresh model.
        $hydrated = $this->service->findWithAudit($case->id);

        return (new MedicalCaseResource($hydrated))->response();
    }

    public function store(StoreCaseRequest $request): JsonResponse
    {
        $case = $this->service->create($request->validated(), $request->user());

        return (new MedicalCaseResource($case))->response()->setStatusCode(201);
    }

    public function update(UpdateCaseRequest $request, MedicalCase $case): JsonResponse
    {
        $updated = $this->service->update($case, $request->validated());

        return (new MedicalCaseResource($updated))->response();
    }

    public function destroy(Request $request, MedicalCase $case): JsonResponse
    {
        $this->authorize('delete', $case);

        $this->service->softDelete($case, $request->user());

        return response()->json(null, 204);
    }

    // ----- case-workflow actions -------------------------------------------

    public function approve(Request $request, MedicalCase $case): JsonResponse
    {
        $this->authorize('approve', $case);

        $updated = $this->service->approve($case, $request->user());

        return (new MedicalCaseResource($updated))->response();
    }

    public function sendBack(SendBackCaseRequest $request, MedicalCase $case): JsonResponse
    {
        $updated = $this->service->sendBack(
            case: $case,
            actor: $request->user(),
            reason: $request->string('reason')->toString(),
        );

        return (new MedicalCaseResource($updated))->response();
    }

    public function rerunAi(Request $request, MedicalCase $case): JsonResponse
    {
        $this->authorize('rerunAi', $case);

        $updated = $this->service->rerunAi($case, $request->user());

        return (new MedicalCaseResource($updated))->response();
    }
}
