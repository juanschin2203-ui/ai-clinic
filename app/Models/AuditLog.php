<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog — append-only HIPAA audit trail.
 *
 * Does NOT use BelongsToTenant global scope intentionally: the auditor
 * (Deployer role) needs to see rows across all clinics. Step 4's middleware
 * enforces that only admin/deployer roles can hit the audit endpoints.
 *
 * No UPDATED_AT — every state change is a new row, never a mutation.
 * (Override: this is one of the very few models where we override the
 *  camelCase timestamp convention — there IS no updated time by design.)
 */
class AuditLog extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'userId',
        'clinicId',
        'action',
        'entityType',
        'entityId',
        'isPhiAccess',
        'ipAddress',
        'userAgent',
        'requestId',
        'before',
        'after',
        'metadata',
        'occurredAt',
    ];

    protected $casts = [
        'isPhiAccess' => 'boolean',
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'occurredAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    /** Polymorphic target — morphMap registered in AppServiceProvider. */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entityType', 'entityId');
    }
}
