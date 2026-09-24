<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppointmentsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\CasesController;
use App\Http\Controllers\Api\ClinicsController;
use App\Http\Controllers\Api\FilesController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Ops\OpsController;
use App\Http\Controllers\Api\PatientsController;
use App\Http\Controllers\Api\ProvidersController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// Public — health check (no auth; load balancers hit this)
// ============================================================================
Route::get('/health', HealthController::class)->middleware('throttle:60,1');

// ============================================================================
// Public auth routes
// ============================================================================
Route::prefix('auth')->middleware('throttle:20,1')->group(function (): void {
    // throttle:login → stacked per-IP + per-email limit (RateLimitServiceProvider).
    Route::post('/login',           LoginController::class)->middleware('throttle:login');
    Route::post('/refresh',         RefreshController::class)->middleware('throttle:refresh');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:forgot-password');
    Route::post('/reset-password',  ResetPasswordController::class)->middleware('throttle:5,15');
});

// ============================================================================
// Authenticated routes — Sanctum bearer + tenant scope populated.
// ============================================================================
Route::middleware(['auth:sanctum', 'tenant-context'])->group(function (): void {

    // ----- auth / session --------------------------------------------------
    Route::prefix('auth')->group(function (): void {
        Route::post('/logout', LogoutController::class);
        Route::get('/me',      MeController::class);
    });

    // ----- clinics ---------------------------------------------------------
    Route::apiResource('clinics', ClinicsController::class);

    // ----- users -----------------------------------------------------------
    Route::apiResource('users', UsersController::class);

    // ----- providers -------------------------------------------------------
    Route::apiResource('providers', ProvidersController::class);

    // ----- patients (PHI) --------------------------------------------------
    Route::apiResource('patients', PatientsController::class);

    // ----- appointments ----------------------------------------------------
    Route::apiResource('appointments', AppointmentsController::class);

    // ----- cases -----------------------------------------------------------
    Route::post('/cases/{case}/approve',   [CasesController::class, 'approve']);
    Route::post('/cases/{case}/send-back', [CasesController::class, 'sendBack']);
    Route::post('/cases/{case}/rerun-ai',  [CasesController::class, 'rerunAi']);
    Route::apiResource('cases', CasesController::class)->parameters(['cases' => 'case']);

    // ----- services (reference catalog) ------------------------------------
    Route::get('/services', [ServicesController::class, 'index']);

    // ----- files (multipart upload; streaming download; audit-logged) ------
    Route::get('/files/{file}/download', [FilesController::class, 'download'])->name('files.download');
    Route::apiResource('files', FilesController::class)->except(['update']);

    // ----- audit reports (deployer / admin only) ---------------------------
    Route::prefix('audit')->middleware('role:admin,deployer')->group(function (): void {
        Route::get('/hipaa',    [AuditController::class, 'hipaa']);
        Route::get('/soc2',     [AuditController::class, 'soc2']);
        Route::get('/activity', [AuditController::class, 'activity']);
    });

    // ----- ops dashboards (deployer / admin only) --------------------------
    Route::prefix('ops')->middleware('role:admin,deployer')->group(function (): void {
        Route::get('/queue/depth',       [OpsController::class, 'queueDepth']);
        Route::get('/ai/token-usage',    [OpsController::class, 'tokenUsage']);
        Route::get('/errors',            [OpsController::class, 'errors']);
        Route::post('/cases/{case}/reprocess', [OpsController::class, 'reprocessCase']);
    });
});
