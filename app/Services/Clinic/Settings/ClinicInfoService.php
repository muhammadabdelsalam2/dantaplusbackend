<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\ClinicInfoResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;

class ClinicInfoService
{
    public function __construct(private ClinicSettingsRepositoryInterface $repository)
    {
    }

    public function show(): array
    {
        $clinic = $this->resolveClinic();

        if (! $clinic) {
            return ServiceResult::error('Clinic not found.', null, null, 404);
        }

        return ServiceResult::success((new ClinicInfoResource($clinic))->resolve(), 'Clinic info fetched successfully');
    }

    public function update(array $data): array
    {
        $clinic = $this->resolveClinic();

        if (! $clinic) {
            return ServiceResult::error('Clinic not found.', null, null, 404);
        }

        $updated = $this->repository->updateClinic($clinic, $data);

        return ServiceResult::success((new ClinicInfoResource($updated))->resolve(), 'Clinic info updated successfully');
    }

    private function resolveClinic()
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return null;
        }

        return $this->repository->findClinicById($clinicId);
    }
}
