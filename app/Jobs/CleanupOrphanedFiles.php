<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\File;
use App\Services\Files\FileService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableConcern;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily janitor job. Deletes File rows (and their storage payloads) where
 * the polymorphic owner was hard-deleted — e.g., a case was purged and
 * left its chart uploads behind.
 *
 * Runs against `maintenance` queue so it never competes with user-facing
 * `default` or `ai-pipeline` work.
 */
class CleanupOrphanedFiles implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableConcern;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(FileService $files): void
    {
        $cutoff = CarbonImmutable::now()->subDays(7);
        $checked = 0;
        $deleted = 0;

        File::withoutGlobalScopes()
            ->where('createdAt', '<', $cutoff)
            ->orderBy('createdAt')
            ->chunkById(200, function ($batch) use ($files, &$checked, &$deleted) {
                foreach ($batch as $file) {
                    $checked++;
                    if ($this->isOrphaned($file)) {
                        $files->delete($file);
                        $deleted++;
                    }
                }
            });

        ActivityLog::query()->create([
            'category' => 'maintenance',
            'event' => 'files.cleanup.completed',
            'severity' => 'info',
            'message' => "Orphan file cleanup — checked {$checked}, deleted {$deleted}.",
            'context' => ['checked' => $checked, 'deleted' => $deleted],
            'occurredAt' => CarbonImmutable::now(),
        ]);
    }

    private function isOrphaned(File $file): bool
    {
        $ownerClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel(
            $file->ownerType ?? ''
        ) ?? $file->ownerType;

        if (! class_exists((string) $ownerClass)) {
            // Unknown owner type — treat as orphan.
            return true;
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $ownerClass */
        $exists = $ownerClass::withoutGlobalScopes()->whereKey($file->ownerId)->exists();

        return ! $exists;
    }
}
