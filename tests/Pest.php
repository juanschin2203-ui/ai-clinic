<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pest configuration. Every Feature test gets a fresh in-memory SQLite DB
 * via RefreshDatabase — tests never touch the dev MySQL.
 *
 * Unit tests under tests/Unit/ use plain PHPUnit without DB access by
 * default (add ->uses(TestCase::class, RefreshDatabase::class) per-test
 * if needed).
 */
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
| Shared custom matchers live here. Add as needed.
*/

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Resolve a raw JSON response body as an associative array.
 */
function jsonBody(\Illuminate\Testing\TestResponse $response): array
{
    return json_decode($response->getContent() ?: '{}', true) ?? [];
}
