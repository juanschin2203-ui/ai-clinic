<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Http\JsonResponse;

/**
 * Services catalog — reference data. Read-only to clinic users. Admin writes
 * land in Step 5b if needed; the current catalog is seeded via Step 3.
 */
class ServicesController extends Controller
{
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $grouped = $this->services->activeByCategory();

        $payload = $grouped->map(
            fn ($items) => ServiceResource::collection($items)->toArray(request()),
        );

        return response()->json(['data' => $payload]);
    }
}
