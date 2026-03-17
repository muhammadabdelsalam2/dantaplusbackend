<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\UploadGalleryRequest;
use App\Services\Lab\Settings\GalleryService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    use ApiResponse;

    public function __construct(private GalleryService $galleryService)
    {
    }

    public function index(Request $request)
    {
        $result = $this->galleryService->listImages($request->only(['type']));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(UploadGalleryRequest $request)
    {
        $result = $this->galleryService->uploadImages($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $image)
    {
        $result = $this->galleryService->deleteImage($image);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
