<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Provider;
use App\Repositories\Contracts\ProviderRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProviderRepository extends BaseRepository implements ProviderRepositoryInterface
{
    protected function model(): string
    {
        return Provider::class;
    }

    public function findByNpi(string $npi): ?Provider
    {
        /** @var Provider|null */
        return $this->query()->where('npi', $npi)->first();
    }

    public function activeForClinic(string $clinicId): Collection
    {
        return $this->query()
            ->where('clinic', $clinicId)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }
}
