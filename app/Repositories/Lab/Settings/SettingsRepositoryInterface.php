<?php

namespace App\Repositories\Lab\Settings;

use App\Models\DentalLab;
use App\Models\LabGalleryImage;
use App\Models\LabSetting;
use App\Models\LabWhatsAppApiLog;
use Illuminate\Support\Collection;

interface SettingsRepositoryInterface
{
    public function getOrCreateSettings(int $labId, array $defaults = []): LabSetting;

    public function findSettingsByLab(int $labId): ?LabSetting;

    public function updateSettings(LabSetting $setting, array $data): LabSetting;

    public function listGalleryImages(int $labId, ?string $type = null): Collection;

    public function createGalleryImage(array $data): LabGalleryImage;

    public function findGalleryImageForLab(int $labId, int $imageId): ?LabGalleryImage;

    public function deleteGalleryImage(LabGalleryImage $image): void;

    public function createWhatsappLog(array $data): LabWhatsAppApiLog;

    public function listWhatsappLogs(int $labId, int $limit = 50): Collection;

    public function findLabById(int $labId): ?DentalLab;

    public function updateLab(DentalLab $lab, array $data): DentalLab;

    public function listAllSettings(): Collection;
}
