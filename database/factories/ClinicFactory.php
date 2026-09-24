<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Medical',
            'state' => fake()->randomElement(['CA', 'TX', 'NY', 'FL', 'IL']),
            'email' => fake()->unique()->companyEmail(),
            'active' => true,
            'city' => fake()->city(),
            'addr' => fake()->streetAddress(),
            'zip' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'npi' => (string) fake()->unique()->numerify('##########'),
            'taxId' => fake()->unique()->numerify('##-#######'),
            'timezone' => 'America/Los_Angeles',
            'selfCoded' => false,
            'reportFavs' => ['initial', 'followup'],
            'notif' => [
                'caseStatus' => true, 'overdue' => true, 'aiFlags' => false,
                'payments' => true, 'channelEmail' => true, 'channelSMS' => false,
            ],
        ];
    }

    public function selfCoded(): static
    {
        return $this->state(['selfCoded' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
