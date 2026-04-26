<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\AppointmentSettingsResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;

class ClinicAppointmentSettingsService
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
            (new AppointmentSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Appointment settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $data = $this->normalizePayload($data);

        foreach ($data as $key => $value) {
            $this->repository->upsertSetting($clinicId, 'appointments', $key, $value);
        }

        return ServiceResult::success(
            (new AppointmentSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Appointment settings updated successfully'
        );
    }

    private function mapSettings(int $clinicId): array
    {
        return $this->repository->getSettingsGroup($clinicId, 'appointments')
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->all();
    }

    private function normalizePayload(array $data): array
    {
        if (array_key_exists('default_duration', $data) && ! array_key_exists('slot_duration', $data)) {
            $data['slot_duration'] = $data['default_duration'];
        }

        if (array_key_exists('slot_duration', $data) && ! array_key_exists('default_duration', $data)) {
            $data['default_duration'] = $data['slot_duration'];
        }

        if (array_key_exists('allow_overlap', $data) && ! array_key_exists('allow_overbooking', $data)) {
            $data['allow_overbooking'] = $data['allow_overlap'];
        }

        if (array_key_exists('allow_overbooking', $data) && ! array_key_exists('allow_overlap', $data)) {
            $data['allow_overlap'] = $data['allow_overbooking'];
        }

        return $data;
    }
}
