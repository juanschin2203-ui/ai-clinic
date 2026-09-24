<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ClinicAdminRepositoryInterface;
use App\Repositories\Contracts\ClinicRepositoryInterface;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\MedicalCaseRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ProviderRepositoryInterface;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentAppointmentRepository;
use App\Repositories\Eloquent\EloquentClinicAdminRepository;
use App\Repositories\Eloquent\EloquentClinicRepository;
use App\Repositories\Eloquent\EloquentFileRepository;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Repositories\Eloquent\EloquentMedicalCaseRepository;
use App\Repositories\Eloquent\EloquentPatientRepository;
use App\Repositories\Eloquent\EloquentProviderRepository;
use App\Repositories\Eloquent\EloquentRefreshTokenRepository;
use App\Repositories\Eloquent\EloquentServiceRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires repository interfaces to their Eloquent implementations. Every new
 * repository adds its interface -> impl binding here.
 *
 * Using bind() (not singleton()) because repositories are stateless — a fresh
 * instance per resolution is fine and avoids surprising state leaks between
 * requests in queue workers.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ClinicRepositoryInterface::class => EloquentClinicRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        RefreshTokenRepositoryInterface::class => EloquentRefreshTokenRepository::class,
        PatientRepositoryInterface::class => EloquentPatientRepository::class,
        ProviderRepositoryInterface::class => EloquentProviderRepository::class,
        AppointmentRepositoryInterface::class => EloquentAppointmentRepository::class,
        MedicalCaseRepositoryInterface::class => EloquentMedicalCaseRepository::class,
        ClinicAdminRepositoryInterface::class => EloquentClinicAdminRepository::class,
        ServiceRepositoryInterface::class => EloquentServiceRepository::class,
        FileRepositoryInterface::class => EloquentFileRepository::class,
        InvoiceRepositoryInterface::class => EloquentInvoiceRepository::class,
    ];

    public function register(): void
    {
        // Bindings declared in $bindings are auto-registered by Laravel.
    }

    public function boot(): void
    {
        //
    }
}
