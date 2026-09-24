<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Abstract Eloquent-backed repository. Concrete repositories extend this and
 * bind their specific Model class via the model() method.
 *
 * The TenantScope global scope is applied automatically to any tenant-scoped
 * model (see BelongsToTenant trait) — repositories don't need to filter by
 * clinicId explicitly. If a concrete repository needs cross-tenant reads
 * (Rocket admin operations), inject and use TenantContext::runWithoutTenant().
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Return the FQCN of the Eloquent model this repository wraps.
     *
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    protected function newModel(): Model
    {
        /** @var Model $instance */
        $instance = app($this->model());

        return $instance;
    }

    public function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    public function find(string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(string $id): Model
    {
        $model = $this->find($id);

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel($this->model(), [$id]);
        }

        return $model;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function paginate(int $perPage = 25, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns);
    }

    public function create(array $attributes): Model
    {
        $model = $this->newModel()->newInstance();
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function update(string $id, array $attributes): Model
    {
        $model = $this->findOrFail($id);
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function delete(string $id): bool
    {
        $model = $this->findOrFail($id);

        return (bool) $model->delete();
    }
}
