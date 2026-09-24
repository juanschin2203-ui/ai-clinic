<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LogoutRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class LogoutController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function __invoke(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->auth->logout(
            user: $user,
            rawRefreshToken: $request->refreshToken(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Logged out.'], 204);
    }
}
