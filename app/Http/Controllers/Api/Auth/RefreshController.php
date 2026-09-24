<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\AuthTokensResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class RefreshController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function __invoke(RefreshRequest $request): JsonResponse
    {
        $pair = $this->auth->refresh(
            rawToken: $request->refreshToken(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new AuthTokensResource($pair))->response()->setStatusCode(200);
    }
}
