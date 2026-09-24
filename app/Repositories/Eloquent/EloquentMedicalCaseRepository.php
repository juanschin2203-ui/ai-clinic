<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CaseStatus;
use App\Models\MedicalCase;
use App\Repositories\Contracts\MedicalCaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentMedicalCaseRepository extends BaseRepository implements MedicalCaseRepositoryInterface
{
    protected function model(): string
    {
        return MedicalCase::class;
    }

    public function paginateWithFilters(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tab'])) {
            match ($filters['tab']) {
                'open' => $query->whereIn('status', [
                    CaseStatus::Processing->value,
                    CaseStatus::NeedsReview->value,
                    CaseStatus::PendingDiagnosisApproval->value,
                ]),
                'completed' => $query->whereIn('status', [
                    CaseStatus::Completed->value,
                    CaseStatus::Delivered->value,
                ]),
                default => null,   // 'all' = no filter
            };
        }

        if (! empty($filters['assignedCoderId'])) {
            $query->where('assignedCoderId', $filters['assignedCoderId']);
        }

        if (! empty($filters['reportType'])) {
            $query->where('reportType', $filters['reportType']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('num', 'like', "%{$search}%")
                    ->orWhere('patient', 'like', "%{$search}%")
                    ->orWhere('claimNum', 'like', "%{$search}%")
                    ->orWhere('emrId', 'like', "%{$search}%");
            });
        }

        return $query->latest('createdAt')->paginate($perPage);
    }

    public function findByNum(string $num): ?MedicalCase
    {
        /** @var MedicalCase|null */
        return $this->query()->where('num', $num)->first();
    }
}
