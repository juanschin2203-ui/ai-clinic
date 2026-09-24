<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthTokensResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $pair = $this->auth->login(
            email: $request->email(),
            password: $request->password(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new AuthTokensResource($pair))->response()->setStatusCode(200);
    }
}
