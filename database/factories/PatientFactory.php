<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinicId' => Clinic::factory(),
            'providerId' => null,
            'name' => fake()->name(),
            'dob' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'emrId' => 'EMR-'.fake()->unique()->numerify('#####'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'employer' => fake()->company(),
            'doi' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'claimNum' => 'WC-2026-'.fake()->numerify('#####'),
            'reportStatus' => fake()->randomElement(['needs_report', 'in_progress', 'completed']),
        ];
    }

    public function inClinic(Clinic $clinic): static
    {
        return $this->state(['clinicId' => $clinic->id]);
    }
}
