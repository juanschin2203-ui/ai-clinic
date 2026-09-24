<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $name = fake()->name();
        $initials = collect(explode(' ', $name))
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'emailVerifiedAt' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'clinic',
            'cid' => Clinic::factory(),
            'initials' => $initials,
            'permission' => 'full',
            'active' => true,
            'lastLogin' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'rememberToken' => null,
            'loginAttempts' => 0,
            'lockedUntil' => null,
        ];
    }

    public function clinic(): static
    {
        return $this->state(['role' => 'clinic']);
    }

    public function provider(): static
    {
        return $this->state(['role' => 'provider']);
    }

    public function staff(): static
    {
        return $this->state(['role' => 'staff', 'permission' => 'scheduler']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin', 'cid' => null]);
    }

    public function deployer(): static
    {
        return $this->state(['role' => 'deployer', 'cid' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function coder(): static
    {
        return $this->state(['permission' => 'coder']);
    }

    public function viewer(): static
    {
        return $this->state(['permission' => 'viewer']);
    }

    public function inClinic(Clinic $clinic): static
    {
        return $this->state(['cid' => $clinic->id]);
    }

    public function withPassword(string $plain): static
    {
        return $this->state(['password' => Hash::make($plain)]);
    }

    public function locked(int $minutesFromNow = 15): static
    {
        return $this->state([
            'loginAttempts' => 3,
            'lockedUntil' => now()->addMinutes($minutesFromNow),
        ]);
    }
}
