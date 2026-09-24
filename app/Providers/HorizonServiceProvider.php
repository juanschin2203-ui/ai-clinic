<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Who can hit /horizon in non-local environments.
     *
     * Admins and Deployers only. The gate receives the authenticated user
     * (web session — the dashboard doesn't use Sanctum tokens); null when
     * unauthenticated → deny.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            if ($user === null) {
                return false;
            }

            return in_array(
                $user->role,
                [UserRole::Admin, UserRole::Deployer],
                strict: true,
            );
        });
    }
}
