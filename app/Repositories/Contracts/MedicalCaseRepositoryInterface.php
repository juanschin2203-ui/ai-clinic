<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\MedicalCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MedicalCaseRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate cases honoring the current TenantContext. `$filters` is the
     * normalized query-param array from the request: ?status, ?assignedCoderId,
     * ?tab (open|completed|all), ?selfCoded, ?clinicId (admin cross-tenant only),
     * ?search, ?reportType.
     */
    public function paginateWithFilters(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findByNum(string $num): ?MedicalCase;
}
