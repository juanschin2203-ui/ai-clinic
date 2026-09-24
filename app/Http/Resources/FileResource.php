<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read File $resource
 */
class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var File $file */
        $file = $this->resource;

        return [
            'id' => $file->id,
            'clinicId' => $file->clinicId,
            'uploaderId' => $file->uploaderId,
            'ownerType' => $file->ownerType,
            'ownerId' => $file->ownerId,
            'kind' => $file->kind,
            'filename' => $file->filename,
            'mimeType' => $file->mimeType,
            'sizeBytes' => (int) $file->sizeBytes,
            'containsPhi' => (bool) $file->containsPhi,
            'checksum' => $file->checksum,
            // NB: s3Bucket + s3Key are deliberately NOT exposed in the API —
            // those are server-side storage implementation details.
            'downloadUrl' => route('files.download', ['file' => $file->id]),
            'createdAt' => $file->createdAt?->toIso8601String(),
        ];
    }
}
