<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface extends RepositoryInterface
{
    /** Calendar-range query: appointments between two dates (inclusive). */
    public function between(string $from, string $to): Collection;
}
