<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Services\Files\FileService;
use App\Services\Phi\PhiAccessRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    public function __construct(
        private readonly FileRepositoryInterface $files,
        private readonly FileService $fileService,
        private readonly PhiAccessRecorder $phi,
    ) {}

    /**
     * List files. Optional filters: ?ownerType=medical_case&ownerId=<uuid>
     * for attachment-style lookup.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', File::class);

        if ($request->filled(['ownerType', 'ownerId'])) {
            $rows = $this->files->forOwner(
                $request->string('ownerType')->toString(),
                $request->string('ownerId')->toString(),
            );

            return FileResource::collection($rows);
        }

        return FileResource::collection(
            $this->files->query()->latest('createdAt')->limit(200)->get(),
        );
    }

    /**
     * Return metadata for a file. PHI read is audited if containsPhi=true.
     */
    public function show(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        if ($file->containsPhi) {
            $this->phi->record($file);
        }

        return (new FileResource($file))->response();
    }

    /**
     * Upload a new file (multipart form-data). Returns metadata; no bytes.
     */
    public function store(StoreFileRequest $request): JsonResponse
    {
        $file = $this->fileService->store(
            upload: $request->file('file'),
            kind: $request->string('kind')->toString(),
            ownerType: $request->string('ownerType')->toString(),
            ownerId: $request->string('ownerId')->toString(),
            uploader: $request->user(),
        );

        return (new FileResource($file))->response()->setStatusCode(201);
    }

    /**
     * Stream the file bytes back to the caller. Fires PhiAccessed if the
     * file is PHI — this is the read event that shows up in HIPAA reports.
     */
    public function download(Request $request, File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        if ($file->containsPhi) {
            $this->phi->record($file);
        }

        $stream = $this->fileService->openStream($file);
        if ($stream === null) {
            abort(404, 'File payload is missing from storage.');
        }

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $file->mimeType,
                'Content-Disposition' => 'attachment; filename="'.addslashes($file->filename).'"',
                'Content-Length' => (string) $file->sizeBytes,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $this->fileService->delete($file);

        return response()->json(null, 204);
    }
}
