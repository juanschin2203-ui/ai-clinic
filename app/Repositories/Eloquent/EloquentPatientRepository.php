<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    protected function model(): string
    {
        return Patient::class;
    }

    public function paginateWithFilters(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->query();

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('emrId', 'like', "%{$search}%")
                    ->orWhere('claimNum', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['reportStatus'])) {
            $query->where('reportStatus', $filters['reportStatus']);
        }

        if (! empty($filters['providerId'])) {
            $query->where('providerId', $filters['providerId']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function findByEmrId(string $clinicId, string $emrId): ?Patient
    {
        /** @var Patient|null */
        return $this->query()
            ->where('clinicId', $clinicId)
            ->where('emrId', $emrId)
            ->first();
    }
}
