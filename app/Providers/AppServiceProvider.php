<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Services\Anthropic\AnthropicClient;
use App\Services\Anthropic\AnthropicClientInterface;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TenantContext is a singleton — same instance for the entire request/job.
        $this->app->singleton(TenantContext::class);

        // Anthropic API client — tests bind FakeAnthropicClient over this
        // binding in their setUp, so this default never fires during CI.
        $this->app->bind(AnthropicClientInterface::class, AnthropicClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use our UUID-aware access token model (Sanctum default is bigint PK).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Eloquent lazy-loading is a footgun in request handlers (N+1 queries
        // silently pile up). Force eager-load everywhere; any code that needs
        // a relation has to explicitly request it with ->load() or ->with().
        Model::preventLazyLoading(! $this->app->isProduction());

        // Silent attribute-missing and silent-strict-mode are also disabled —
        // we want LOUD failures in dev so bugs surface early.
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Polymorphic relation short names. `ownerType` on files, audit_logs,
        // activity_logs stores these instead of fully-qualified class names.
        Relation::enforceMorphMap([
            'clinic' => \App\Models\Clinic::class,
            'user' => \App\Models\User::class,
            'provider' => \App\Models\Provider::class,
            'patient' => \App\Models\Patient::class,
            'medical_case' => \App\Models\MedicalCase::class,
            'appointment' => \App\Models\Appointment::class,
            'invoice' => \App\Models\Invoice::class,
            'payment' => \App\Models\Payment::class,
            'file' => \App\Models\File::class,
        ]);
    }
}
