<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\File;
use App\Repositories\Contracts\FileRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentFileRepository extends BaseRepository implements FileRepositoryInterface
{
    protected function model(): string
    {
        return File::class;
    }

    public function forOwner(string $ownerType, string $ownerId): Collection
    {
        return $this->query()
            ->where('ownerType', $ownerType)
            ->where('ownerId', $ownerId)
            ->latest('createdAt')
            ->get();
    }
}
