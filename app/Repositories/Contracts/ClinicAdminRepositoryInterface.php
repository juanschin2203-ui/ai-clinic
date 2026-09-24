<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ClinicAdminRepositoryInterface extends RepositoryInterface
{
    public function forClinic(string $clinicId): Collection;
}
