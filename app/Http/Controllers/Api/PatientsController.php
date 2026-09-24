<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Services\Phi\PhiAccessRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientsController extends Controller
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly PhiAccessRecorder $phi,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $filters = $request->only(['search', 'reportStatus', 'providerId']);
        $paginator = $this->patients->paginateWithFilters($filters, (int) $request->integer('perPage', 25));

        return PatientResource::collection($paginator)->response();
    }

    public function show(Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        $patient->loadMissing('provider');
        $this->phi->record($patient);

        return (new PatientResource($patient))->response();
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        /** @var Patient $patient */
        $patient = $this->patients->create($request->validated());
        $patient->loadMissing('provider');

        return (new PatientResource($patient))->response()->setStatusCode(201);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        /** @var Patient $updated */
        $updated = $this->patients->update($patient->id, $request->validated());
        $updated->loadMissing('provider');

        return (new PatientResource($updated))->response();
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $this->authorize('delete', $patient);

        $this->patients->delete($patient->id);

        return response()->json(null, 204);
    }
}
