<?php

declare(strict_types=1);

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()
        ->inClinic($this->clinic)
        ->withPassword('pw123')
        ->create();

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'pw123',
    ]);
    $this->token = $login->json('data.accessToken');

    $this->patient = Patient::factory()->inClinic($this->clinic)->create([
        'name' => 'Test Patient',
        'emrId' => 'EMR-TEST-1',
    ]);
});

it('lists patients scoped to the current clinic', function () {
    $otherClinic = Clinic::factory()->create();
    Patient::factory()->inClinic($otherClinic)->create(['name' => 'Other Patient']);

    $response = $this->withToken($this->token)->getJson('/api/patients');

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Test Patient');
    expect($names)->not->toContain('Other Patient');
});

it('shows a patient with JSX-shaped fields', function () {
    $response = $this->withToken($this->token)->getJson("/api/patients/{$this->patient->id}");

    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'dob', 'emrId', 'phone', 'employer', 'doi', 'provider', 'reportStatus'],
        ])
        ->assertJsonPath('data.name', 'Test Patient');
});

it('creates a patient with clinicId inferred from the current tenant', function () {
    $response = $this->withToken($this->token)->postJson('/api/patients', [
        'name' => 'New Patient',
        'emrId' => 'EMR-NEW-1',
        'dob' => '1980-05-15',
        'employer' => 'Acme Co',
        'doi' => '2026-01-10',
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'New Patient')
        ->assertJsonPath('data.clinicId', $this->clinic->id);
});

it('updates a patient', function () {
    $response = $this->withToken($this->token)->patchJson("/api/patients/{$this->patient->id}", [
        'reportStatus' => 'completed',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.reportStatus', 'completed');
});

it('rejects showing another clinic\'s patient', function () {
    $otherClinic = Clinic::factory()->create();
    $otherPatient = Patient::factory()->inClinic($otherClinic)->create();

    $response = $this->withToken($this->token)->getJson("/api/patients/{$otherPatient->id}");

    expect($response->status())->toBeIn([403, 404]);
});
