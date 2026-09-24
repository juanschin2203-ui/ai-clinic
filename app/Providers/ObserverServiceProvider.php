<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Appointment;
use App\Models\File;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Observers\PhiObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Attaches the PhiObserver to every PHI-bearing Eloquent model.
 *
 * Adding a new PHI model?
 *   1. Add the `// PHI` comment to the migration.
 *   2. Register the model in the $phiModels array below.
 *   3. Done — every create/update/delete on it auto-audits.
 */
class ObserverServiceProvider extends ServiceProvider
{
    private const PHI_MODELS = [
        Patient::class,
        MedicalCase::class,
        Appointment::class,
        File::class,
    ];

    public function boot(): void
    {
        foreach (self::PHI_MODELS as $modelClass) {
            $modelClass::observe(PhiObserver::class);
        }
    }
}
