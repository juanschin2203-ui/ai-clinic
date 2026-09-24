<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for all Rocket Coding Eloquent models EXCEPT User (which extends
 * Authenticatable) and the handful of reference-data models that use string
 * PKs (Service, FeeSchedule).
 *
 * Provides:
 *  - UUID primary key (HasUuids)
 *  - camelCase timestamp columns (createdAt / updatedAt / deletedAt)
 *  - strict mass-assignment: nothing is fillable until the model explicitly
 *    whitelists (overrides Laravel's default $guarded = ['*'])
 */
abstract class AbstractRocketModel extends Model
{
    use HasFactory;
    use HasUuids;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    public const DELETED_AT = 'deletedAt';

    /**
     * Default to closed fillable — each model whitelists. Prevents accidental
     * mass-assignment of sensitive fields (password hashes, tenant FKs, audit
     * metadata, etc).
     */
    protected $guarded = ['id', 'createdAt', 'updatedAt', 'deletedAt'];
}
