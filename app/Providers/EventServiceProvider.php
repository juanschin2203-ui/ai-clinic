<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Placeholder for future event → listener wiring that's not covered by
 * Laravel's convention-based auto-discovery.
 *
 * Auto-discovery already registers our listeners: any public method in
 * `app/Listeners/**` whose name starts with "handle" and type-hints an
 * event class is bound to that event automatically. `WriteAuthAuditEntry`
 * follows that convention, so we do NOT explicitly `Event::listen()` it
 * here — doing so would register it twice and double every audit row.
 *
 * Listeners that need explicit ordering / queuing / alternate method names
 * belong here in boot().
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // No explicit registrations. See class docblock.
    }
}
