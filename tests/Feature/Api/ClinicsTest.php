<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;

it('lists clinics for admin', function () {
    Clinic::factory()->count(3)->create();
    $admin = User::factory()->admin()->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $response = $this->withToken($token)->getJson('/api/clinics');

    $response
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'name', 'state', 'email', 'active', 'selfCoded']]]);
    expect(count($response->json('data')))->toBe(3);
});

it('rejects clinic list for a clinic user with 403', function () {
    $clinic = Clinic::factory()->create();
    $clinicUser = User::factory()->inClinic($clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', ['email' => $clinicUser->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $this->withToken($token)->getJson('/api/clinics')->assertStatus(403);
});

it('allows a clinic user to view their own clinic', function () {
    $clinic = Clinic::factory()->create();
    $clinicUser = User::factory()->inClinic($clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', ['email' => $clinicUser->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $response = $this->withToken($token)->getJson("/api/clinics/{$clinic->id}");

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.id', $clinic->id);
});

it('rejects a clinic user viewing another clinic', function () {
    $clinic = Clinic::factory()->create();
    $otherClinic = Clinic::factory()->create();
    $clinicUser = User::factory()->inClinic($clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', ['email' => $clinicUser->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $this->withToken($token)->getJson("/api/clinics/{$otherClinic->id}")->assertStatus(403);
});

it('creates a clinic when admin POSTs', function () {
    $admin = User::factory()->admin()->withPassword('pw123')->create();
    $login = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $response = $this->withToken($token)->postJson('/api/clinics', [
        'name' => 'New Clinic',
        'state' => 'CA',
        'email' => 'new@example.com',
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'New Clinic')
        ->assertJsonPath('data.state', 'CA');
});

it('rejects clinic creation by non-admin with 403', function () {
    $clinic = Clinic::factory()->create();
    $clinicUser = User::factory()->inClinic($clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', ['email' => $clinicUser->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $this->withToken($token)->postJson('/api/clinics', [
        'name' => 'X',
        'state' => 'CA',
        'email' => 'x@example.com',
    ])->assertStatus(403);
});

it('updates a clinic when admin PATCHes', function () {
    $admin = User::factory()->admin()->withPassword('pw123')->create();
    $clinic = Clinic::factory()->create(['selfCoded' => false]);

    $login = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $this->withToken($token)->patchJson("/api/clinics/{$clinic->id}", ['selfCoded' => true])
        ->assertStatus(200)
        ->assertJsonPath('data.selfCoded', true);
});
