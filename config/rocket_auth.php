<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Access Token (Sanctum)
    |--------------------------------------------------------------------------
    | Short-lived API token returned on login. Clients refresh via the
    | /auth/refresh endpoint before it expires.
    */
    'access_token_ttl_minutes' => (int) env('SANCTUM_ACCESS_TOKEN_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token
    |--------------------------------------------------------------------------
    | Long-lived rotating token. Stored in the refresh_tokens table as SHA-256
    | hash (never the raw token). Used once — every refresh issues a new pair
    | and marks the old one consumed.
    */
    'refresh_token_ttl_minutes' => (int) env('SANCTUM_REFRESH_TOKEN_TTL', 10080), // 7 days

    /*
    |--------------------------------------------------------------------------
    | Login Rate Limiting
    |--------------------------------------------------------------------------
    | Per-IP AND per-email throttles stacked — a NAT'd corporate network
    | can't bypass per-IP by just rotating source IPs, because per-email
    | also throttles attempts against a single account. Window is 15 min
    | (Laravel's default rate-limiter unit is per-minute, so limit * minutes).
    */
    'login_rate_limit_ip_per_minute' => (int) env('LOGIN_RATE_LIMIT_IP', 5) / 15,
    'login_rate_limit_ip_attempts' => (int) env('LOGIN_RATE_LIMIT_IP', 5),
    'login_rate_limit_email_attempts' => (int) env('LOGIN_RATE_LIMIT_EMAIL', 5),
    'login_rate_limit_window_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Account Lockout
    |--------------------------------------------------------------------------
    | After `lockout_attempts` consecutive failed logins, the account is
    | locked for `lockout_minutes`. Subsequent login attempts get 423 Locked
    | until the cooldown expires. Matches the user manual spec (3 fails,
    | 15 min cooldown).
    */
    'lockout_attempts' => (int) env('LOGIN_LOCKOUT_ATTEMPTS', 3),
    'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    */
    'password_reset_token_ttl_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Token Abilities
    |--------------------------------------------------------------------------
    | Sanctum tokens carry ability arrays. Access tokens get `access` which
    | is the broad "do authenticated stuff" ability; Step 5's endpoints will
    | check more granular abilities (e.g., `cases:approve`, `admin:billing`)
    | via the `ability:*` middleware on individual routes.
    */
    'access_token_abilities' => ['access'],
];
