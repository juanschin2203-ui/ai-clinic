<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * THE MOST IMPORTANT TEST IN THE SYSTEM.
 *
 * If any of these assertions fail, a clinic user can see another clinic's
 * patient data. That is a HIPAA incident. Do not hand-wave failures here —
 * find the root cause and fix it before moving on.
 *
 * Tests exercise the architectural enforcement (Eloquent global scope +
 * SetTenantContext middleware), NOT per-endpoint "did you remember to
 * filter" logic. Because there IS no per-endpoint logic — the scope runs
 * on every query against a BelongsToTenant model, and forgetting to apply
 * it is structurally impossible.
 */

beforeEach(function () {
    $this->valley = Clinic::factory()->create(['name' => 'Valley Medical']);
    $this->coastal = Clinic::factory()->create(['name' => 'Coastal Ortho']);

    $this->valleyUser = User::factory()
        ->inClinic($this->valley)
        ->withPassword('pw123')
        ->create();

    $this->coastalUser = User::factory()
        ->inClinic($this->coastal)
        ->withPassword('pw123')
        ->create();

    $this->valleyPatient = Patient::factory()->inClinic($this->valley)->create();
    $this->coastalPatient = Patient::factory()->inClinic($this->coastal)->create();
});

it('returns only the current tenant\'s data when TenantContext is set to that tenant', function () {
    // Valley user logs in → SetTenantContext middleware pins TenantContext to Valley.
    $login = $this->postJson('/api/auth/login', [
        'email' => $this->valleyUser->email,
        'password' => 'pw123',
    ]);
    $accessToken = $login->json('data.accessToken');

    // Anything run inside this authenticated request that queries Patient
    // will only see Valley's patient. Simulate via a tiny inline test route.
    \Illuminate\Support\Facades\Route::middleware(['auth:sanctum', 'tenant-context'])
        ->get('/api/_test/patients', fn () => response()->json([
            'count' => \App\Models\Patient::count(),
            'ids' => \App\Models\Patient::pluck('id')->all(),
        ]));

    $response = $this->withToken($accessToken)->getJson('/api/_test/patients');

    $response->assertStatus(200);
    expect($response->json('count'))->toBe(1);
    expect($response->json('ids'))->toBe([$this->valleyPatient->id]);
    expect($response->json('ids'))->not->toContain($this->coastalPatient->id);
});

it('returns a different tenant\'s view when the other user logs in', function () {
    \Illuminate\Support\Facades\Route::middleware(['auth:sanctum', 'tenant-context'])
        ->get('/api/_test/patients', fn () => response()->json([
            'count' => \App\Models\Patient::count(),
            'ids' => \App\Models\Patient::pluck('id')->all(),
        ]));

    $coastalLogin = $this->postJson('/api/auth/login', [
        'email' => $this->coastalUser->email,
        'password' => 'pw123',
    ]);
    $coastalAccess = $coastalLogin->json('data.accessToken');

    $response = $this->withToken($coastalAccess)->getJson('/api/_test/patients');

    expect($response->json('count'))->toBe(1);
    expect($response->json('ids'))->toBe([$this->coastalPatient->id]);
    expect($response->json('ids'))->not->toContain($this->valleyPatient->id);
});

it('filters model-level queries via the global scope when TenantContext is set programmatically', function () {
    $ctx = app(TenantContext::class);

    // No tenant set — no filter
    expect(Patient::count())->toBe(2);

    // Valley tenant — 1 row
    $ctx->setClinicId($this->valley->id);
    expect(Patient::count())->toBe(1);
    expect(Patient::pluck('id')->all())->toBe([$this->valleyPatient->id]);

    // Coastal tenant — 1 row (different row)
    $ctx->setClinicId($this->coastal->id);
    expect(Patient::count())->toBe(1);
    expect(Patient::pluck('id')->all())->toBe([$this->coastalPatient->id]);

    // Bypass for admin ops — sees everything again
    $ctx->runWithoutTenant(function () {
        expect(Patient::count())->toBe(2);
    });

    $ctx->reset();
});

it('aborts a clinic-role user with no cid with 403 (data integrity guard)', function () {
    // Construct a malformed user: role=clinic but cid=null (SHOULD never happen
    // in production — but if it ever did, the middleware rejects rather than
    // leaking cross-tenant data by defaulting to "no tenant = no filter").
    $brokenUser = User::factory()->create([
        'role' => 'clinic',
        'cid' => null,
        'active' => true,
        'password' => \Illuminate\Support\Facades\Hash::make('pw123'),
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => $brokenUser->email,
        'password' => 'pw123',
    ]);
    $accessToken = $login->json('data.accessToken');

    $this->withToken($accessToken)
        ->getJson('/api/auth/me')
        ->assertStatus(403);
});

it('admins do not have TenantContext set and can see across tenants', function () {
    $adminUser = User::factory()->admin()->withPassword('pw123')->create();

    \Illuminate\Support\Facades\Route::middleware(['auth:sanctum', 'tenant-context'])
        ->get('/api/_test/patients', fn () => response()->json([
            'count' => \App\Models\Patient::count(),
        ]));

    $login = $this->postJson('/api/auth/login', [
        'email' => $adminUser->email,
        'password' => 'pw123',
    ]);
    $accessToken = $login->json('data.accessToken');

    $response = $this->withToken($accessToken)->getJson('/api/_test/patients');

    // Admin has no tenant set → global scope no-ops → sees all patients.
    expect($response->json('count'))->toBe(2);
});
