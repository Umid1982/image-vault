<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Image\ImageFilterRequest;
use App\Http\Requests\Image\ImageUploadRequest;
use App\Http\Resources\Resources\ImageResource;
use App\Http\Traits\ApiResponseHelper;
use App\Http\Traits\ApiResponsePaginate;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;

class ImageController extends Controller
{
    use ApiResponseHelper, ApiResponsePaginate;

    public function __construct(protected readonly ImageService $imageService)
    {
    }

    public function index(ImageFilterRequest $request): JsonResponse
    {
        $images = $this->imageService->list($request->filters());

        return $images->isNotEmpty()
            ? $this->paginate(ImageResource::collection($images))
            : $this->errorResponse('Images not found');
    }

    /**
     * @param ImageUploadRequest $request
     * @return JsonResponse
     */
    public function store(ImageUploadRequest $request): JsonResponse
    {
        $image = $this->imageService->upload($request->toArray());

        return $image
            ? $this->successResponse(ImageResource::make($image))
            : $this->errorResponse('Upload failed', 422);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $image = $this->imageService->get($id);

        return $image
            ? $this->successResponse(ImageResource::make($image))
            : $this->errorResponse('Image not found', 404);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        return $this->imageService->delete($id)
            ? $this->successResponse(['message' => 'Image deleted'])
            : $this->errorResponse('Delete failed', 422);
    }
}
