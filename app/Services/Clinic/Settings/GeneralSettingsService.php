<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\GeneralSettingsResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;

class GeneralSettingsService
{
    public function __construct(private ClinicSettingsRepositoryInterface $repository)
    {
    }

    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new GeneralSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'General settings fetched successfully'
        );
    }

    public function update(array $data, $logo = null): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $existing = $this->mapSettings($clinicId);
        $payload = $existing;

        if ($logo) {
            $payload['logo'] = asset('storage/' . $logo->store('clinics/logos', 'public'));
        }

        foreach ($data as $key => $value) {
            $payload[$key] = $value;
        }

        foreach ($payload as $key => $value) {
            $this->repository->upsertSetting($clinicId, 'general', $key, $value);
        }

        return ServiceResult::success(
            (new GeneralSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'General settings updated successfully'
        );
    }

    private function mapSettings(int $clinicId): array
    {
        return $this->repository->getSettingsGroup($clinicId, 'general')
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->all();
    }
}
