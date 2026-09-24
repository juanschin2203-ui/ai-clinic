<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The named `login` limiter stacks per-IP + per-email so a single IP can
 * hit many accounts and a single account can be hit from many IPs, but
 * both constraints have separate budgets that independently trigger 429.
 *
 * Direct test of the limiter definition (not via HTTP) — the base TestCase
 * disables ThrottleRequests middleware for speed, so the HTTP stack
 * wouldn't exercise it in feature tests.
 */

it('registers the login limiter with per-IP AND per-email limits', function () {
    $request = Request::create('/api/auth/login', 'POST', [
        'email' => 'test@example.com',
    ]);
    $request->server->set('REMOTE_ADDR', '10.0.0.7');

    $callback = RateLimiter::limiter('login');
    expect($callback)->toBeCallable();

    $limits = $callback($request);
    expect($limits)->toBeArray();
    expect(count($limits))->toBe(2);
    expect($limits[0])->toBeInstanceOf(Limit::class);
    expect($limits[1])->toBeInstanceOf(Limit::class);

    // The two limit keys should differ — one IP-scoped, one email-scoped.
    expect($limits[0]->key)->not->toBe($limits[1]->key);
    expect($limits[0]->key)->toContain('10.0.0.7');
    expect($limits[1]->key)->toContain('test@example.com');
});

it('lowercases + trims the email in the per-email bucket', function () {
    $request = Request::create('/api/auth/login', 'POST', [
        'email' => '  TEST@Example.COM  ',
    ]);

    $callback = RateLimiter::limiter('login');
    $limits = $callback($request);

    expect($limits[1]->key)->toContain('test@example.com');
    expect($limits[1]->key)->not->toContain('TEST@Example.COM');
});

it('registers the forgot-password limiter with per-IP and per-email stacks', function () {
    $request = Request::create('/api/auth/forgot-password', 'POST', [
        'email' => 'forgot@example.com',
    ]);
    $request->server->set('REMOTE_ADDR', '10.0.0.8');

    $callback = RateLimiter::limiter('forgot-password');
    $limits = $callback($request);

    expect(count($limits))->toBe(2);
});

it('registers the refresh limiter with per-IP only', function () {
    $request = Request::create('/api/auth/refresh', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.9');

    $callback = RateLimiter::limiter('refresh');
    $limits = $callback($request);

    expect(count($limits))->toBe(1);
});
