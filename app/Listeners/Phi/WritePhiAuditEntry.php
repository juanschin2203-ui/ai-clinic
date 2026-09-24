<?php

declare(strict_types=1);

namespace App\Listeners\Phi;

use App\Events\Phi\PhiAccessed;
use App\Events\Phi\PhiCreated;
use App\Events\Phi\PhiDeleted;
use App\Events\Phi\PhiUpdated;
use App\Models\AbstractRocketModel;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Converts PHI events into audit_logs rows with isPhiAccess=true.
 *
 * One listener class, four handler methods. Laravel 11+ auto-discovers these
 * via the `handle*` naming convention + event-type-hinted first parameter.
 *
 * Every row here has isPhiAccess=true — this is what the Deployer HIPAA
 * report groups by. SOC 2 reporting also reads this table.
 */
class WritePhiAuditEntry
{
    public function handlePhiAccessed(PhiAccessed $event): void
    {
        $this->write(
            action: 'read',
            model: $event->model,
            before: null,
            after: null,
            userId: $event->userId,
            clinicId: $event->clinicId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            requestId: $event->requestId,
        );
    }

    public function handlePhiCreated(PhiCreated $event): void
    {
        $this->write(
            action: 'create',
            model: $event->model,
            before: null,
            after: $event->after,
            userId: $event->userId,
            clinicId: $event->clinicId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            requestId: $event->requestId,
        );
    }

    public function handlePhiUpdated(PhiUpdated $event): void
    {
        $this->write(
            action: 'update',
            model: $event->model,
            before: $event->before,
            after: $event->after,
            userId: $event->userId,
            clinicId: $event->clinicId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            requestId: $event->requestId,
        );
    }

    public function handlePhiDeleted(PhiDeleted $event): void
    {
        $this->write(
            action: $event->hardDelete ? 'hard_delete' : 'delete',
            model: $event->model,
            before: $event->before,
            after: null,
            userId: $event->userId,
            clinicId: $event->clinicId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            requestId: $event->requestId,
        );
    }

    private function write(
        string $action,
        AbstractRocketModel $model,
        ?array $before,
        ?array $after,
        ?string $userId,
        ?string $clinicId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $requestId,
    ): void {
        AuditLog::query()->create([
            'userId' => $userId,
            'clinicId' => $clinicId,
            'action' => $action,
            'entityType' => $this->morphNameFor($model),
            'entityId' => $model->getKey(),
            'isPhiAccess' => true,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'requestId' => $requestId,
            'before' => $before,
            'after' => $after,
            'metadata' => null,
            'occurredAt' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Convert an Eloquent model class back to its short morph alias (e.g.
     * 'medical_case'). AppServiceProvider calls enforceMorphMap([...]) which
     * populates Relation::$morphMap; we read it here for the reverse lookup.
     */
    private function morphNameFor(AbstractRocketModel $model): string
    {
        $class = $model::class;
        $map = Relation::morphMap();

        $alias = array_search($class, $map, strict: true);

        return $alias !== false ? $alias : $class;
    }
}
