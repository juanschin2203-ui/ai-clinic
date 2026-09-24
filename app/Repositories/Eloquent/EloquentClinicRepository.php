<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Clinic;
use App\Repositories\Contracts\ClinicRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Concrete Eloquent impl of the Clinic repository contract. Serves as the
 * canonical example for the pattern — Step 4 and Step 5 will produce
 * analogous repositories for User, MedicalCase, Patient, Invoice, etc.
 */
class EloquentClinicRepository extends BaseRepository implements ClinicRepositoryInterface
{
    protected function model(): string
    {
        return Clinic::class;
    }

    public function findByEmail(string $email): ?Clinic
    {
        /** @var Clinic|null */
        return $this->query()->where('email', $email)->first();
    }

    public function findByNpi(string $npi): ?Clinic
    {
        /** @var Clinic|null */
        return $this->query()->where('npi', $npi)->first();
    }

    public function active(): Collection
    {
        return $this->query()->where('active', true)->orderBy('name')->get();
    }

    public function selfCoded(): Collection
    {
        return $this->query()->where('selfCoded', true)->where('active', true)->get();
    }

    public function rocketCoded(): Collection
    {
        return $this->query()->where('selfCoded', false)->where('active', true)->get();
    }

    public function byState(string $stateCode): Collection
    {
        return $this->query()->where('state', $stateCode)->get();
    }
}
