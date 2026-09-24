<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentAppointmentRepository extends BaseRepository implements AppointmentRepositoryInterface
{
    protected function model(): string
    {
        return Appointment::class;
    }

    public function between(string $from, string $to): Collection
    {
        return $this->query()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }
}
