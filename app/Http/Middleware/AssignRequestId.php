<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a per-request UUID (or honors an incoming X-Request-Id header) so
 * every audit row, log line, and downstream span can be correlated to the
 * same HTTP request. Runs first in the global pipeline.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');

        $requestId = (is_string($incoming) && $incoming !== '' && strlen($incoming) <= 40)
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set('X-Request-Id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
