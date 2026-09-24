<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Base repository contract. Every concrete repository (ClinicRepository,
 * UserRepository, MedicalCaseRepository, ...) extends a specialized interface
 * that extends this one.
 *
 * Controllers depend on the *interface*, never the concrete class — so we
 * can swap Eloquent for a Redis-backed or test-stubbed impl without touching
 * controllers or services.
 */
interface RepositoryInterface
{
    public function find(string $id): ?Model;

    public function findOrFail(string $id): Model;

    public function all(array $columns = ['*']): Collection;

    public function paginate(int $perPage = 25, array $columns = ['*']): LengthAwarePaginator;

    public function create(array $attributes): Model;

    public function update(string $id, array $attributes): Model;

    public function delete(string $id): bool;

    public function query(): \Illuminate\Database\Eloquent\Builder;
}
