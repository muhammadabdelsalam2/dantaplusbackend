<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\AppearanceSettingsResource;
use App\Models\AppearanceSetting;
use App\Models\Setting;
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
        $platformCustomization = Setting::query()
            ->where('scope_type', 'platform')
            ->whereNull('scope_id')
            ->where('group', 'customization')
            ->get(['key', 'value'])
            ->pluck('value', 'key');

        $theme = $platformCustomization->get('dashboard_theme', 'light');
        $theme = $theme === 'auto' ? 'light' : $theme;

        return AppearanceSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'theme' => in_array($theme, ['light', 'dark'], true) ? $theme : 'light',
                'primary_color' => strtoupper((string) $platformCustomization->get('accent_color', '#6C5CE7')),
                'language' => 'en',
            ]
        );
    }
}
