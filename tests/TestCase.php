<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Wipe the array cache so nothing bleeds between tests.
        Cache::flush();

        // Disable HTTP-level rate-limit middleware in tests. Rate-limiting is
        // infrastructure — its correctness belongs in integration/E2E tests,
        // not in every feature test. Without this, making >5 login POSTs
        // across a test suite starts returning 429 and masks real assertions.
        $this->withoutMiddleware([ThrottleRequests::class]);
    }
}
