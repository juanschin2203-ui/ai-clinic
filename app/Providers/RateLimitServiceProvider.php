<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate limiters for login. The route layer uses `throttle:login` which
 * resolves to this definition.
 *
 * Two limiter keys stacked per request:
 *   1. IP address   — protects the whole login endpoint from one source
 *   2. Email (lowercased, trimmed)
 *                    — protects a single account from brute-force across
 *                      NAT'd corporate networks where many users share one IP
 *
 * If EITHER limit is exceeded, the request is blocked. Configurable via
 * config/rocket_auth.php — production can tighten both.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));
            $ipAttempts = (int) config('rocket_auth.login_rate_limit_ip_attempts', 5);
            $emailAttempts = (int) config('rocket_auth.login_rate_limit_email_attempts', 5);
            $windowMinutes = (int) config('rocket_auth.login_rate_limit_window_minutes', 15);

            return [
                Limit::perMinutes($windowMinutes, $ipAttempts)
                    ->by('login_ip:'.$request->ip())
                    ->response(function () {
                        return response()->json([
                            'error' => [
                                'code' => 'too_many_requests',
                                'message' => 'Too many login attempts from this IP. Try again later.',
                            ],
                        ], 429);
                    }),
                Limit::perMinutes($windowMinutes, $emailAttempts)
                    ->by('login_email:'.$email)
                    ->response(function () {
                        return response()->json([
                            'error' => [
                                'code' => 'too_many_requests',
                                'message' => 'Too many login attempts for this account. Try again later.',
                            ],
                        ], 429);
                    }),
            ];
        });

        // Refresh: same IP-throttle shape but more permissive (legitimate
        // clients may refresh frequently).
        RateLimiter::for('refresh', function (Request $request): array {
            return [
                Limit::perMinute(30)->by('refresh_ip:'.$request->ip()),
            ];
        });

        // Forgot-password: tighter, per-email, to slow enumeration + abuse.
        RateLimiter::for('forgot-password', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinutes(15, 3)->by('fp_ip:'.$request->ip()),
                Limit::perMinutes(15, 3)->by('fp_email:'.$email),
            ];
        });
    }
}
