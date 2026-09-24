<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();

    Route::middleware(['auth:sanctum', 'role:admin'])
        ->get('/api/_test/admin-only', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'role:admin,deployer'])
        ->get('/api/_test/admin-or-deployer', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'clinic'])
        ->get('/api/_test/clinic-only', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'permission:coder,full'])
        ->get('/api/_test/coder-only', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'tenant:clinicId'])
        ->get('/api/_test/clinics/{clinicId}/data', fn ($clinicId) => response()->json(['ok' => true, 'cid' => $clinicId]));
});

function loginAs(\App\Models\User $user): string
{
    $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('pw123')])->save();

    $response = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'pw123',
    ]);

    return $response->json('data.accessToken');
}

// ----- role middleware -----------------------------------------------------

it('role middleware accepts allowed role', function () {
    $admin = User::factory()->admin()->create();
    $token = loginAs($admin);

    $this->withToken($token)->getJson('/api/_test/admin-only')->assertStatus(200);
});

it('role middleware rejects wrong role with 403', function () {
    $clinic = User::factory()->inClinic($this->clinic)->create();
    $token = loginAs($clinic);

    $this->withToken($token)->getJson('/api/_test/admin-only')->assertStatus(403);
});

it('role middleware accepts any of multiple allowed roles', function () {
    $deployer = User::factory()->deployer()->create();
    $token = loginAs($deployer);

    $this->withToken($token)->getJson('/api/_test/admin-or-deployer')->assertStatus(200);
});

// ----- clinic middleware ---------------------------------------------------

it('clinic middleware accepts a user with cid', function () {
    $user = User::factory()->inClinic($this->clinic)->create();
    $token = loginAs($user);

    $this->withToken($token)->getJson('/api/_test/clinic-only')->assertStatus(200);
});

it('clinic middleware rejects an admin with no cid with 403', function () {
    $admin = User::factory()->admin()->create();
    $token = loginAs($admin);

    $this->withToken($token)->getJson('/api/_test/clinic-only')->assertStatus(403);
});

// ----- permission middleware ----------------------------------------------

it('permission middleware accepts allowed permission', function () {
    $coder = User::factory()->inClinic($this->clinic)->coder()->create();
    $token = loginAs($coder);

    $this->withToken($token)->getJson('/api/_test/coder-only')->assertStatus(200);
});

it('permission middleware rejects viewer-level user with 403', function () {
    $viewer = User::factory()->inClinic($this->clinic)->viewer()->create();
    $token = loginAs($viewer);

    $this->withToken($token)->getJson('/api/_test/coder-only')->assertStatus(403);
});

it('permission middleware bypasses for admin regardless of permission', function () {
    $admin = User::factory()->admin()->create(['permission' => 'viewer']);
    $token = loginAs($admin);

    $this->withToken($token)->getJson('/api/_test/coder-only')->assertStatus(200);
});

// ----- tenant-match middleware --------------------------------------------

it('tenant-match accepts when route clinicId equals user cid', function () {
    $user = User::factory()->inClinic($this->clinic)->create();
    $token = loginAs($user);

    $this->withToken($token)
        ->getJson("/api/_test/clinics/{$this->clinic->id}/data")
        ->assertStatus(200);
});

it('tenant-match rejects cross-tenant path parameter with 403', function () {
    $otherClinic = Clinic::factory()->create();
    $user = User::factory()->inClinic($this->clinic)->create();
    $token = loginAs($user);

    $this->withToken($token)
        ->getJson("/api/_test/clinics/{$otherClinic->id}/data")
        ->assertStatus(403);
});

it('tenant-match allows admin to hit any clinic', function () {
    $admin = User::factory()->admin()->create();
    $token = loginAs($admin);

    $anyClinic = Clinic::factory()->create();

    $this->withToken($token)
        ->getJson("/api/_test/clinics/{$anyClinic->id}/data")
        ->assertStatus(200);
});
