<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed`. Runs reference data first (codes,
 * fee schedules, EMRs) so the prototype data can reference it; then runs
 * the prototype seeder (clinics / users / cases / ...).
 *
 * Both seeders are idempotent via updateOrCreate on natural keys — running
 * twice does not create duplicates.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            PrototypeSeeder::class,
        ]);
    }
}
