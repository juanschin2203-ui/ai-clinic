<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientRepositoryInterface extends RepositoryInterface
{
    /** Paginate patients, filtered implicitly by the current TenantContext. */
    public function paginateWithFilters(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findByEmrId(string $clinicId, string $emrId): ?Patient;
}
