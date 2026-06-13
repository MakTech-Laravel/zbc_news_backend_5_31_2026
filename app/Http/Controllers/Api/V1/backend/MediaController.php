<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadMediaRequest;
use App\Http\Resources\Api\V1\MediaResource;
use App\Models\Media;
use App\Services\CloudinaryService;
use App\Services\MediaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MediaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'media_type' => 'nullable|in:image,video,document,audio,archive,other',
            'collection' => 'nullable|string',
            'status' => 'nullable|in:pending,uploading,ready,failed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $media = $this->mediaService->listForUser($request->user()->id, $request->only([
            'media_type', 'collection', 'status', 'per_page',
        ]));

        return sendResponse(
            true,
            'Media retrieved successfully',
            MediaResource::collection($media),
            HttpStatus::HTTP_OK
        );
    }

    public function store(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $collection = $request->input('collection', 'default');
        $async = $request->boolean('async', false);
        $options = [
            'folder' => $request->input('folder'),
            'collection' => $collection,
        ];

        $media = $this->mediaService->createPlaceholder(
            $file,
            $request->user()->id,
            $collection,
            $request->input('folder'),
            $request->input('mediable_type'),
            $request->input('mediable_id')
        );

        if ($async) {
            $this->mediaService->queueUpload($media, $file, $options);

            return sendResponse(
                true,
                'Upload queued.',
                new MediaResource($media),
                HttpStatus::HTTP_ACCEPTED
            );
        }

        try {
            $media = $this->mediaService->uploadSync($media, $file, $options);

            return sendResponse(
                true,
                'Uploaded successfully.',
                new MediaResource($media),
                HttpStatus::HTTP_CREATED
            );
        } catch (\Throwable $e) {
            $media->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return sendResponse(
                false,
                'Upload failed.',
                ['error' => $e->getMessage()],
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        $media->load('transformations', 'uploader');

        return sendResponse(
            true,
            'Media retrieved successfully',
            new MediaResource($media),
            HttpStatus::HTTP_OK
        );
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        $deleted = $this->mediaService->deleteFromCloudinary($media);

        if ($deleted) {
            $media->update(['status' => 'deleted']);
            $media->delete();

            return sendResponse(true, 'Deleted successfully.', null, HttpStatus::HTTP_OK);
        }

        return sendResponse(false, 'Cloudinary deletion failed.', null, HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function signedParams(Request $request): JsonResponse
    {
        $params = $this->cloudinary->generateSignedUploadParams([
            'folder' => $request->input('folder', config('cloudinary.folder')),
        ]);

        return sendResponse(true, 'Signed upload params generated.', $params, HttpStatus::HTTP_OK);
    }

    public function transform(Request $request, Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        $request->validate([
            'preset' => 'required|string|in:thumbnail,avatar,banner,preview,hd',
        ]);

        $result = $this->mediaService->getOrCreateTransformation($media, $request->input('preset'));

        return sendResponse(true, 'Transformation URL generated.', $result, HttpStatus::HTTP_OK);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|max:50', 'ids.*' => 'string']);

        $media = Media::whereIn('uuid', $request->ids)
            ->where('uploaded_by', $request->user()->id)
            ->get();

        $count = $this->mediaService->bulkDelete($media);

        return sendResponse(true, 'Bulk delete completed.', ['count' => $count], HttpStatus::HTTP_OK);
    }
}
