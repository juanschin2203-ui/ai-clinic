<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Provider;
use Illuminate\Support\Collection;

interface ProviderRepositoryInterface extends RepositoryInterface
{
    public function findByNpi(string $npi): ?Provider;

    public function activeForClinic(string $clinicId): Collection;
}
