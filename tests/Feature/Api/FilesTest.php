<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\File as RcFile;
use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->clinic = Clinic::factory()->create();
    $this->user = User::factory()->inClinic($this->clinic)->withPassword('pw123')->create();

    $login = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'pw123',
    ]);
    $this->token = $login->json('data.accessToken');
});

it('uploads a file and returns metadata (not bytes)', function () {
    $case = MedicalCase::withoutGlobalScopes()->create([
        'num' => 'SNP-UP-1',
        'clinic' => $this->clinic->id,
        'clinicName' => $this->clinic->name,
        'patient' => 'Test',
        'state' => 'CA',
        'status' => 'processing',
        'reportType' => 'initial',
        'inputSource' => 'upload',
        'total' => 0,
    ]);

    $response = $this->withToken($this->token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf'),
        'kind' => 'chart',
        'ownerType' => 'medical_case',
        'ownerId' => $case->id,
    ], ['Accept' => 'application/json']);

    $response
        ->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'clinicId', 'ownerType', 'ownerId', 'kind', 'filename',
                'mimeType', 'sizeBytes', 'containsPhi', 'checksum', 'downloadUrl'],
        ])
        ->assertJsonPath('data.kind', 'chart')
        ->assertJsonPath('data.containsPhi', true)           // chart kind is PHI
        ->assertJsonPath('data.filename', 'chart.pdf')
        ->assertJsonPath('data.ownerType', 'medical_case');
});

it('marks non-PHI file kinds as containsPhi=false', function () {
    $response = $this->withToken($this->token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf'),
        'kind' => 'invoicePdf',
        'ownerType' => 'clinic',
        'ownerId' => $this->clinic->id,
    ], ['Accept' => 'application/json']);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.containsPhi', false);
});

it('streams the file back on download with the correct mime type', function () {
    $upload = $this->withToken($this->token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        'kind' => 'chart',
        'ownerType' => 'patient',
        'ownerId' => (string) \Illuminate\Support\Str::uuid(),
    ], ['Accept' => 'application/json']);

    $id = $upload->json('data.id');

    $download = $this->withToken($this->token)->get("/api/files/{$id}/download");
    $download->assertStatus(200)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('writes a PHI audit entry when downloading a PHI file', function () {
    $upload = $this->withToken($this->token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'kind' => 'chart',
        'ownerType' => 'patient',
        'ownerId' => (string) \Illuminate\Support\Str::uuid(),
    ], ['Accept' => 'application/json']);
    $id = $upload->json('data.id');

    $before = AuditLog::where('isPhiAccess', true)->where('action', 'read')->count();

    $this->withToken($this->token)->get("/api/files/{$id}/download")->assertStatus(200);

    $after = AuditLog::where('isPhiAccess', true)->where('action', 'read')->count();
    expect($after)->toBe($before + 1);
});

it('does not write a PHI audit entry for non-PHI file downloads', function () {
    $upload = $this->withToken($this->token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('inv.pdf', 10, 'application/pdf'),
        'kind' => 'invoicePdf',
        'ownerType' => 'clinic',
        'ownerId' => $this->clinic->id,
    ], ['Accept' => 'application/json']);
    $id = $upload->json('data.id');

    $before = AuditLog::where('isPhiAccess', true)->where('action', 'read')->count();

    $this->withToken($this->token)->get("/api/files/{$id}/download")->assertStatus(200);

    $after = AuditLog::where('isPhiAccess', true)->where('action', 'read')->count();
    expect($after)->toBe($before);
});

it('rejects cross-tenant file view with 403 or 404', function () {
    $otherClinic = Clinic::factory()->create();
    $file = RcFile::withoutGlobalScopes()->create([
        'clinicId' => $otherClinic->id,
        'ownerType' => 'clinic',
        'ownerId' => $otherClinic->id,
        'kind' => 'chart',
        'filename' => 'other.pdf',
        'mimeType' => 'application/pdf',
        'sizeBytes' => 100,
        's3Key' => 'files/other/chart.pdf',
        'containsPhi' => true,
    ]);

    $response = $this->withToken($this->token)->getJson("/api/files/{$file->id}");
    expect($response->status())->toBeIn([403, 404]);
});

it('rejects upload for a viewer-permission user with 403', function () {
    $viewer = User::factory()->inClinic($this->clinic)->viewer()->withPassword('pw123')->create();
    $login = $this->postJson('/api/auth/login', ['email' => $viewer->email, 'password' => 'pw123']);
    $token = $login->json('data.accessToken');

    $this->withToken($token)->post('/api/files', [
        'file' => UploadedFile::fake()->create('x.pdf', 10),
        'kind' => 'chart',
        'ownerType' => 'clinic',
        'ownerId' => $this->clinic->id,
    ], ['Accept' => 'application/json'])->assertStatus(403);
});

it('validates required fields on upload (422)', function () {
    $response = $this->withToken($this->token)->postJson('/api/files', [
        'kind' => 'not-a-valid-kind',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file', 'kind', 'ownerType', 'ownerId']);
});
