<?php

namespace App\Repositories\Lab\Settings;

use App\Models\DentalLab;
use App\Models\LabGalleryImage;
use App\Models\LabSetting;
use App\Models\LabWhatsAppApiLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SettingsRepository implements SettingsRepositoryInterface
{
    public function getOrCreateSettings(int $labId, array $defaults = []): LabSetting
    {
        return LabSetting::query()->firstOrCreate(['lab_id' => $labId], $defaults);
    }

    public function findSettingsByLab(int $labId): ?LabSetting
    {
        return LabSetting::query()->where('lab_id', $labId)->first();
    }

    public function updateSettings(LabSetting $setting, array $data): LabSetting
    {
        $setting->update($data);

        return $setting->refresh();
    }

    public function listGalleryImages(int $labId, ?string $type = null): Collection
    {
        return LabGalleryImage::query()
            ->where('lab_id', $labId)
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    public function createGalleryImage(array $data): LabGalleryImage
    {
        return LabGalleryImage::query()->create($data);
    }

    public function findGalleryImageForLab(int $labId, int $imageId): ?LabGalleryImage
    {
        return LabGalleryImage::query()
            ->where('lab_id', $labId)
            ->where('id', $imageId)
            ->first();
    }

    public function deleteGalleryImage(LabGalleryImage $image): void
    {
        $image->delete();
    }

    public function createWhatsappLog(array $data): LabWhatsAppApiLog
    {
        return LabWhatsAppApiLog::query()->create($data);
    }

    public function listWhatsappLogs(int $labId, int $limit = 50): Collection
    {
        return LabWhatsAppApiLog::query()
            ->where('lab_id', $labId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function findLabById(int $labId): ?DentalLab
    {
        return DentalLab::query()->find($labId);
    }

    public function updateLab(DentalLab $lab, array $data): DentalLab
    {
        $lab->update($data);

        return $lab->refresh();
    }

    public function listAllSettings(): Collection
    {
        return LabSetting::query()->get();
    }
}
