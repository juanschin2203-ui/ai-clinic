<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Phi\PhiAccessRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentsController extends Controller
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly PhiAccessRecorder $phi,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);

        // Calendar range query: ?from=YYYY-MM-DD&to=YYYY-MM-DD.
        if ($request->filled('from') && $request->filled('to')) {
            $rows = $this->appointments->between(
                $request->string('from')->toString(),
                $request->string('to')->toString(),
            );

            return AppointmentResource::collection($rows);
        }

        return AppointmentResource::collection(
            $this->appointments->query()->orderBy('date')->orderBy('time')->get(),
        );
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        $this->phi->record($appointment);

        return (new AppointmentResource($appointment))->response();
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointments->create($request->validated());

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $updated = $this->appointments->update($appointment->id, $request->validated());

        return (new AppointmentResource($updated))->response();
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $this->appointments->delete($appointment->id);

        return response()->json(null, 204);
    }
}
