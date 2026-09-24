<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\StoreProviderRequest;
use App\Http\Requests\Providers\UpdateProviderRequest;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use App\Repositories\Contracts\ProviderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProvidersController extends Controller
{
    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Provider::class);

        $query = $this->providers->query();

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return ProviderResource::collection($query->orderBy('name')->get());
    }

    public function show(Provider $provider): JsonResponse
    {
        $this->authorize('view', $provider);

        return (new ProviderResource($provider))->response();
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        $provider = $this->providers->create($request->validated());

        return (new ProviderResource($provider))->response()->setStatusCode(201);
    }

    public function update(UpdateProviderRequest $request, Provider $provider): JsonResponse
    {
        $updated = $this->providers->update($provider->id, $request->validated());

        return (new ProviderResource($updated))->response();
    }

    public function destroy(Provider $provider): JsonResponse
    {
        $this->authorize('delete', $provider);

        $this->providers->delete($provider->id);

        return response()->json(null, 204);
    }
}
