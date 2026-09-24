<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\File;
use App\Models\User;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload/download/delete for the Files resource.
 *
 * Storage layout (disk::private/files):
 *   {clinicId}/{YYYY}/{MM}/{uuid}.{ext}
 *
 * Chart uploads, dictation recordings → containsPhi=true (driven by `kind`).
 * Chart PDFs of invoices, batch exports → containsPhi=false.
 *
 * Raw file content NEVER lives in the DB. Only metadata (filename, size,
 * checksum, s3Key). The actual bytes live on the configured filesystem
 * (dev: local disk; prod: S3 with KMS encryption).
 */
class FileService
{
    /**
     * Kinds that carry PHI payload. Drives the containsPhi flag + whether
     * downloads fire PhiAccessed events.
     */
    private const PHI_KINDS = ['chart', 'dictation', 'emrExport'];

    public function __construct(
        private readonly FileRepositoryInterface $files,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Store an uploaded file. Returns the File model (metadata row).
     */
    public function store(
        UploadedFile $upload,
        string $kind,
        string $ownerType,
        string $ownerId,
        User $uploader,
        ?string $clinicIdOverride = null,
    ): File {
        $clinicId = $clinicIdOverride ?? $this->tenant->getClinicId() ?? $uploader->cid;

        $disk = $this->disk();
        $extension = $upload->getClientOriginalExtension() ?: 'bin';
        $now = CarbonImmutable::now();
        $relativePath = sprintf(
            'files/%s/%s/%s/%s.%s',
            $clinicId ?? 'shared',
            $now->format('Y'),
            $now->format('m'),
            (string) Str::uuid(),
            $extension,
        );

        // Store the file bytes.
        $disk->putFileAs(dirname($relativePath), $upload, basename($relativePath));

        return $this->files->create([
            'clinicId' => $clinicId,
            'uploaderId' => $uploader->id,
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
            'kind' => $kind,
            'filename' => $upload->getClientOriginalName(),
            'mimeType' => $upload->getClientMimeType() ?: 'application/octet-stream',
            'sizeBytes' => $upload->getSize() ?: 0,
            's3Bucket' => config('filesystems.disks.'.$this->diskName().'.bucket'),
            's3Key' => $relativePath,
            'checksum' => hash_file('sha256', $upload->getRealPath()),
            'encryptionRef' => null,          // Populated when disk=s3 with KMS
            'containsPhi' => in_array($kind, self::PHI_KINDS, strict: true),
        ]);
    }

    /**
     * Open a read stream for a file's bytes. Caller streams to the client.
     * Returns null if the file is missing from storage (orphaned metadata).
     *
     * @return resource|null
     */
    public function openStream(File $file)
    {
        $disk = $this->disk();
        if (! $disk->exists($file->s3Key)) {
            return null;
        }

        return $disk->readStream($file->s3Key);
    }

    /**
     * Remove a file from both DB and storage. Storage delete is best-effort;
     * the metadata row is always removed.
     */
    public function delete(File $file): void
    {
        $this->disk()->delete($file->s3Key);
        $this->files->delete($file->id);
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        // File payloads are private by default. Production flips this to 's3'.
        return (string) config('filesystems.default', 'local');
    }
}
