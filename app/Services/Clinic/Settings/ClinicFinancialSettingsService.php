<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\FinancialSettingsResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;

class ClinicFinancialSettingsService
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
            (new FinancialSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Financial settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        foreach ($data as $key => $value) {
            $this->repository->upsertSetting($clinicId, 'financial', $key, $value);
        }

        return ServiceResult::success(
            (new FinancialSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Financial settings updated successfully'
        );
    }

    private function mapSettings(int $clinicId): array
    {
        return $this->repository->getSettingsGroup($clinicId, 'financial')
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->all();
    }
}
