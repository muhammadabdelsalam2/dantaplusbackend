<?php

namespace App\Services\Lab\Settings;

use App\Http\Resources\Lab\Settings\GalleryImageResource;
use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryService
{
    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    public function listImages(array $filters): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $type = $filters['type'] ?? null;

        $images = $this->settingsRepository->listGalleryImages($labId, $type);

        return ServiceResult::success(GalleryImageResource::collection($images)->resolve(), 'Gallery images fetched successfully');
    }

    public function uploadImages(array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $type = $data['type'];
        $files = $data['files'] ?? [];

        $uploaded = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = Storage::disk('public')->putFile('labs/' . $labId . '/gallery/' . $type, $file);
            $url = Storage::disk('public')->url($path);

            $image = $this->settingsRepository->createGalleryImage([
                'lab_id' => $labId,
                'type' => $type,
                'url' => $url,
                'disk' => 'public',
                'sort_order' => null,
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $uploaded[] = $image;
        }

        return ServiceResult::success(
            GalleryImageResource::collection($uploaded)->resolve(),
            'Images uploaded successfully.',
            201
        );
    }

    public function deleteImage(int $imageId): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $image = $this->settingsRepository->findGalleryImageForLab($labId, $imageId);
        if (!$image) {
            return ServiceResult::error('Image not found', null, null, 404);
        }

        $path = $this->extractStoragePath($image->url, $image->disk);
        if ($path && Storage::disk($image->disk)->exists($path)) {
            Storage::disk($image->disk)->delete($path);
        }

        $this->settingsRepository->deleteGalleryImage($image);

        return ServiceResult::success(null, 'Image deleted.');
    }

    private function extractStoragePath(?string $url, string $disk): ?string
    {
        if (!$url) {
            return null;
        }

        $diskUrl = config("filesystems.disks.{$disk}.url");
        if ($diskUrl && str_starts_with($url, $diskUrl)) {
            $url = substr($url, strlen($diskUrl));
        }

        $url = ltrim($url, '/');

        if (str_starts_with($url, 'storage/')) {
            $url = substr($url, strlen('storage/'));
        }

        return $url ?: null;
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
