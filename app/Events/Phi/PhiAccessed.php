<?php

declare(strict_types=1);

namespace App\Events\Phi;

use App\Models\AbstractRocketModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every read of a PHI-bearing model row (Patient, MedicalCase,
 * Appointment, File). `WritePhiAuditEntry` listener converts this into
 * an audit_logs row with `isPhiAccess=true`.
 *
 * "Read" in Rocket Coding means any code path that reveals PHI to a user
 * (controller show endpoints). The model observer does NOT fire on every
 * Eloquent `->first()` because that would flood audit_logs with tenant-
 * scope-filtered nothing-burgers. Reads are emitted at the controller
 * layer via explicit dispatch.
 */
class PhiAccessed
{
    use Dispatchable;

    public function __construct(
        public readonly AbstractRocketModel $model,
        public readonly ?string $userId,
        public readonly ?string $clinicId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $requestId = null,
    ) {}
}
