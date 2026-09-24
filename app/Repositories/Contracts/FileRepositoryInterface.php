<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\File;
use Illuminate\Support\Collection;

interface FileRepositoryInterface extends RepositoryInterface
{
    /** Files attached to a polymorphic owner (e.g., a specific case, patient). */
    public function forOwner(string $ownerType, string $ownerId): Collection;
}
