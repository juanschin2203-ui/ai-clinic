<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * File — polymorphic storage record for uploaded charts, dictation recordings,
 * batch inputs, invoice PDFs, EMR exports. `ownerType` + `ownerId` point at
 * the logical owner (patient, case, clinic, invoice). `s3Key` is the actual
 * storage location.
 *
 * containsPhi=true means the payload itself is PHI (chart uploads, dictation
 * audio). Used by the audit service to gate access + log reads.
 */
class File extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'clinicId',
        'uploaderId',
        'ownerType',
        'ownerId',
        'kind',
        'filename',
        'mimeType',
        'sizeBytes',
        's3Bucket',
        's3Key',
        'checksum',
        'encryptionRef',
        'containsPhi',
    ];

    protected $casts = [
        'sizeBytes' => 'integer',
        'containsPhi' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinicId');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaderId');
    }

    /** Polymorphic — morphMap is registered in AppServiceProvider. */
    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ownerType', 'ownerId');
    }
}
