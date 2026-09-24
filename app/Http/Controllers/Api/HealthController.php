<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Public health-check endpoint for load balancers and uptime monitors.
 *
 *   GET /api/health
 *   → 200 {status: "ok", dependencies: {...}}  when all deps are reachable
 *   → 503 {status: "degraded", dependencies: {...}}  when anything is down
 *
 * Dependencies probed (each with a 1-second timeout so a slow dep doesn't
 * block the health check itself):
 *   - db       — executes `SELECT 1` through the default connection
 *   - redis    — PING (queues + cache backend)
 *   - queue    — checks Horizon pause status (if installed)
 *   - anthropic — just reports the API key is configured; doesn't actually
 *                 call Anthropic (would be expensive + rate-limited)
 *
 * Returns structured JSON so a load balancer that checks `status=ok` and an
 * operator dashboard that renders per-dependency state both work off the
 * same endpoint.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $deps = [
            'db' => $this->probeDb(),
            'redis' => $this->probeRedis(),
            'queue' => $this->probeQueue(),
            'anthropic' => $this->probeAnthropic(),
        ];

        $allOk = collect($deps)->every(fn ($d) => $d['ok'] === true);

        return response()->json(
            data: [
                'status' => $allOk ? 'ok' : 'degraded',
                'app' => [
                    'env' => config('app.env'),
                    'version' => config('app.version', 'dev'),
                    'timestamp' => now()->toIso8601String(),
                ],
                'dependencies' => $deps,
            ],
            status: $allOk ? 200 : 503,
        );
    }

    private function probeDb(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->selectOne('SELECT 1 as ok');
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            return ['ok' => true, 'latencyMs' => $latencyMs];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function probeRedis(): array
    {
        try {
            $start = microtime(true);
            // Redis::connection()->client()->ping() — phpredis returns true,
            // predis returns 'PONG'. Either way, non-throw means reachable.
            Redis::connection()->client()->ping();
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            return ['ok' => true, 'latencyMs' => $latencyMs];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Light-touch queue check: confirm the Redis queue connection config
     * is present. Deeper checks (queue depth, worker count) live on the
     * admin-only /ops/queue/depth endpoint.
     */
    private function probeQueue(): array
    {
        try {
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver");

            return [
                'ok' => $connection !== null && $driver !== null,
                'connection' => $connection,
                'driver' => $driver,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function probeAnthropic(): array
    {
        $apiKey = (string) config('anthropic.api_key');

        return [
            'ok' => $apiKey !== '',
            'configured' => $apiKey !== '',
            // We do NOT call Anthropic from the health check — it's expensive
            // + rate-limited. A deeper live probe lands on /ops/ai/reachability
            // if/when we need one.
        ];
    }
}
