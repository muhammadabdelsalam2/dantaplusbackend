<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\AppearanceSettingsResource;
use App\Models\AppearanceSetting;
use App\Support\ServiceResult;

class ClinicAppearanceSettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new AppearanceSettingsResource($this->settingForClinic($clinicId)))->resolve(),
            'Appearance settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinicId);
        $setting->update([
            'theme' => $data['theme'],
            'primary_color' => strtoupper($data['primary_color']),
            'language' => $this->normalizeLanguage($data['language']),
        ]);

        return ServiceResult::success(
            (new AppearanceSettingsResource($setting->fresh()))->resolve(),
            'Appearance settings updated successfully'
        );
    }

    private function normalizeLanguage(string $language): string
    {
        return match (strtolower($language)) {
            'english' => 'en',
            'arabic' => 'ar',
            default => strtolower($language),
        };
    }

    private function settingForClinic(int $clinicId): AppearanceSetting
    {
        return AppearanceSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'theme' => 'light',
                'primary_color' => '#4F46E5',
                'language' => 'en',
            ]
        );
    }
}
