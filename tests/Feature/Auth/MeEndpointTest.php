<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\User;

it('returns the current user shape matching JSX when authenticated', function () {
    $clinic = Clinic::factory()->create(['name' => 'Test Clinic']);
    $user = User::factory()->inClinic($clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'pw123',
    ]);
    $accessToken = $login->json('data.accessToken');

    $response = $this->withToken($accessToken)->getJson('/api/auth/me');

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'cid', 'cn', 'initials', 'permission', 'active', 'lastLogin'],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.cid', $clinic->id)
        ->assertJsonPath('data.cn', 'Test Clinic');
});

it('returns 401 without bearer token', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});

it('returns 401 with invalid bearer token', function () {
    $this->withToken('000-invalid|abcdef1234')->getJson('/api/auth/me')->assertStatus(401);
});
