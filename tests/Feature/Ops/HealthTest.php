<?php

declare(strict_types=1);

it('returns 200 with ok status when all dependencies are reachable', function () {
    config(['anthropic.api_key' => 'test-key-for-health-check']);

    $response = $this->getJson('/api/health');

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'app' => ['env', 'version', 'timestamp'],
            'dependencies' => [
                'db' => ['ok'],
                'redis' => ['ok'],
                'queue' => ['ok', 'connection', 'driver'],
                'anthropic' => ['ok', 'configured'],
            ],
        ])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('dependencies.db.ok', true);
});

it('returns 503 with degraded status when anthropic api key is missing', function () {
    config(['anthropic.api_key' => '']);

    $response = $this->getJson('/api/health');

    $response
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('dependencies.anthropic.ok', false);
});

it('requires no authentication (load balancer needs to hit it anonymously)', function () {
    $response = $this->getJson('/api/health');

    // 200 or 503 — either way it must NOT be 401.
    expect($response->status())->not->toBe(401);
});
