<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityLog — system-level operational events (distinct from AuditLog which
 * is PHI-centric). Populated from services, jobs, webhooks. Powers the
 * Deployer's Audit tab "System Activity" view.
 *
 * Like AuditLog, has no UPDATED_AT — append-only.
 */
class ActivityLog extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $fillable = [
        'userId',
        'clinicId',
        'category',
        'event',
        'severity',
        'message',
        'context',
        'occurredAt',
    ];

    protected $casts = [
        'context' => 'array',
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
}
