<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\PasswordResetCompleted;
use App\Events\Auth\PasswordResetRequested;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserLoginFailed;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;

/**
 * Single listener that converts every auth event into an audit_logs row.
 *
 * Staying out of controllers — this is the entirety of auth audit coverage.
 * Add a new event class + a new handler method here to cover it; controllers
 * never know audit exists. Per the architecture memory: "Every PHI read/write
 * emits an event; the audit listener persists it. The controller shouldn't
 * know audit exists."
 *
 * Auth events are NOT PHI (no patient data), so `isPhiAccess=false`.
 * They DO go to audit_logs (not activity_logs) because they're user-action
 * audit trail entries, which is what audit_logs is for.
 */
class WriteAuthAuditEntry
{
    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $this->write(
            userId: $event->userId,
            clinicId: $event->clinicId,
            action: 'login',
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            metadata: ['outcome' => 'success'],
        );
    }

    public function handleUserLoginFailed(UserLoginFailed $event): void
    {
        $this->write(
            userId: $event->userId,
            clinicId: null,               // don't know yet / user might not exist
            action: 'login',
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            metadata: [
                'outcome' => 'failure',
                'reason' => $event->reason,
                'email' => $event->email,
            ],
        );
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        $this->write(
            userId: $event->userId,
            clinicId: $event->clinicId,
            action: 'logout',
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
        );
    }

    public function handlePasswordResetRequested(PasswordResetRequested $event): void
    {
        $this->write(
            userId: $event->user->id,
            clinicId: $event->user->cid,
            action: 'password_reset_requested',
            ipAddress: $event->ipAddress,
            userAgent: null,
        );
    }

    public function handlePasswordResetCompleted(PasswordResetCompleted $event): void
    {
        $this->write(
            userId: $event->user->id,
            clinicId: $event->user->cid,
            action: 'password_reset_completed',
            ipAddress: $event->ipAddress,
            userAgent: null,
        );
    }

    private function write(
        ?string $userId,
        ?string $clinicId,
        string $action,
        ?string $ipAddress,
        ?string $userAgent,
        array $metadata = [],
    ): void {
        AuditLog::query()->create([
            'userId' => $userId,
            'clinicId' => $clinicId,
            'action' => $action,
            'entityType' => 'user',
            'entityId' => $userId,
            'isPhiAccess' => false,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'requestId' => request()?->headers->get('X-Request-Id'),
            'metadata' => $metadata,
            'occurredAt' => CarbonImmutable::now(),
        ]);
    }
}
