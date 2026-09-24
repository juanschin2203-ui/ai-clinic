<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinics\StoreClinicRequest;
use App\Http\Requests\Clinics\UpdateClinicRequest;
use App\Http\Resources\ClinicResource;
use App\Models\Clinic;
use App\Repositories\Contracts\ClinicRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClinicsController extends Controller
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinics,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Clinic::class);

        $query = $this->clinics->query()
            ->withCount(['users', 'providers', 'cases']);

        // Gotcha: `filter_var(null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)`
        // returns false for null input (not null). Check has() first so an
        // absent query param does not silently filter to active=false.
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return ClinicResource::collection($query->orderBy('name')->get());
    }

    public function show(Clinic $clinic): JsonResponse
    {
        $this->authorize('view', $clinic);

        $clinic->loadCount(['users', 'providers', 'cases']);

        return (new ClinicResource($clinic))->response();
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $clinic = $this->clinics->create($request->validated());

        return (new ClinicResource($clinic))->response()->setStatusCode(201);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): JsonResponse
    {
        $updated = $this->clinics->update($clinic->id, $request->validated());
        $updated->loadCount(['users', 'providers', 'cases']);

        return (new ClinicResource($updated))->response();
    }

    public function destroy(Clinic $clinic): JsonResponse
    {
        $this->authorize('delete', $clinic);

        $this->clinics->delete($clinic->id);

        return response()->json(null, 204);
    }
}
