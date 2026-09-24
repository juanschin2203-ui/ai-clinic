<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Events\Auth\PasswordResetCompleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;

class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $resets,
    ) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->resets->consume(
            email: $request->email(),
            rawToken: $request->token(),
            newPassword: $request->newPassword(),
        );

        if ($user === null) {
            // Generic failure — don't leak which of (email/token/expiry) was wrong.
            return response()->json([
                'error' => [
                    'code' => 'invalid_reset_token',
                    'message' => 'The reset token is invalid or has expired.',
                ],
            ], 422);
        }

        Event::dispatch(new PasswordResetCompleted(
            user: $user,
            ipAddress: $request->ip(),
        ));

        return response()->json([
            'message' => 'Password updated. Please log in with your new password.',
        ], 200);
    }
}
