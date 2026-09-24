<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Events\Auth\PasswordResetRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;

/**
 * POST /api/auth/forgot-password
 *
 * ALWAYS returns 200 regardless of whether the email matches an active user.
 * This is deliberate — we don't want attackers probing our email list via
 * the forgot-password endpoint. Response message is generic.
 *
 * If the email matches an active user, we issue a reset token and fire
 * PasswordResetRequested (listener emails the user + audits the request).
 */
class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PasswordResetService $resets,
    ) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $user = $this->auth->findUserForReset($request->email());

        if ($user !== null) {
            $rawToken = $this->resets->issue($user);

            Event::dispatch(new PasswordResetRequested(
                user: $user,
                resetToken: $rawToken,
                ipAddress: $request->ip(),
            ));
        }

        // Deliberately generic — no leak about whether the email is registered.
        return response()->json([
            'message' => 'If that email is associated with an active account, a password reset link has been sent.',
        ], 200);
    }
}
