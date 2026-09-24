<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ClinicAdmin;
use App\Repositories\Contracts\ClinicAdminRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentClinicAdminRepository extends BaseRepository implements ClinicAdminRepositoryInterface
{
    protected function model(): string
    {
        return ClinicAdmin::class;
    }

    public function forClinic(string $clinicId): Collection
    {
        return $this->query()
            ->where('clinic', $clinicId)
            ->orderBy('name')
            ->get();
    }
}
