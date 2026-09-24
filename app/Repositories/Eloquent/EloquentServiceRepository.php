<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentServiceRepository extends BaseRepository implements ServiceRepositoryInterface
{
    protected function model(): string
    {
        return Service::class;
    }

    public function activeByCategory(): Collection
    {
        return $this->query()
            ->where('active', true)
            ->orderBy('sortOrder')
            ->orderBy('title')
            ->get()
            ->groupBy('cat');
    }
}
