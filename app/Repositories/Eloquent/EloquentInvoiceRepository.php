<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentInvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    protected function model(): string
    {
        return Invoice::class;
    }

    public function findForPeriod(string $clinicId, string $period): ?Invoice
    {
        /** @var Invoice|null */
        return $this->query()
            ->where('clinicId', $clinicId)
            ->where('period', $period)
            ->first();
    }

    public function dueOnOrBefore(string $date): Collection
    {
        return $this->query()
            ->whereIn('status', ['sent', 'draft'])
            ->where('dueDate', '<=', $date)
            ->get();
    }
}
