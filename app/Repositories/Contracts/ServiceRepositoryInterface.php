<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ServiceRepositoryInterface extends RepositoryInterface
{
    /** All active services, grouped by category for the order-entry UI. */
    public function activeByCategory(): Collection;
}
