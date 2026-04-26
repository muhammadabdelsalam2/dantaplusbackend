<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\IntegrationSettingsResource;
use App\Models\IntegrationSetting;
use App\Support\ServiceResult;
use Illuminate\Support\Str;

class ClinicIntegrationSettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $items = collect(['google', 'outlook'])->map(function (string $provider) use ($clinicId) {
            return $this->settingForProvider($clinicId, $provider);
        });

        return ServiceResult::success([
            'items' => IntegrationSettingsResource::collection($items)->resolve(),
            'more_integrations' => [
                'implemented' => false,
                'message' => 'TODO: more integrations are coming soon.',
            ],
        ], 'Integration settings fetched successfully');
    }

    public function connect(string $provider, array $data = []): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForProvider($clinicId, $provider);
        $setting->update([
            'access_token' => $data['access_token'] ?? 'mock_access_' . Str::random(24),
            'refresh_token' => $data['refresh_token'] ?? 'mock_refresh_' . Str::random(24),
            'connected' => true,
        ]);

        return ServiceResult::success(
            (new IntegrationSettingsResource($setting->fresh()))->resolve(),
            ucfirst($provider) . ' Calendar connected successfully'
        );
    }

    private function settingForProvider(int $clinicId, string $provider): IntegrationSetting
    {
        return IntegrationSetting::query()->firstOrCreate(
            [
                'clinic_id' => $clinicId,
                'provider' => $provider,
            ],
            [
                'connected' => false,
            ]
        );
    }
}
