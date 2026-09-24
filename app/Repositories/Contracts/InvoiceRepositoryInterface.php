<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Support\Collection;

interface InvoiceRepositoryInterface extends RepositoryInterface
{
    public function findForPeriod(string $clinicId, string $period): ?Invoice;

    /** Unpaid invoices for a given run date (used by ACH debit cycle). */
    public function dueOnOrBefore(string $date): Collection;
}
