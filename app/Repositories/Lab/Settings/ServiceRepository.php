<?php

namespace App\Repositories\Lab\Settings;

use App\Models\CaseModel;
use App\Models\LabService;
use Illuminate\Support\Collection;

class ServiceRepository implements ServiceRepositoryInterface
{
    public function listByLab(int $labId): Collection
    {
        return LabService::query()
            ->where('lab_id', $labId)
            ->orderByDesc('id')
            ->get();
    }

    public function findByLabAndId(int $labId, int $serviceId): ?LabService
    {
        return LabService::query()
            ->where('lab_id', $labId)
            ->where('id', $serviceId)
            ->first();
    }

    public function create(array $data): LabService
    {
        return LabService::query()->create($data);
    }

    public function update(LabService $service, array $data): LabService
    {
        $service->update($data);

        return $service->refresh();
    }

    public function delete(LabService $service): void
    {
        $service->delete();
    }

    public function hasActiveCasesForService(int $labId, string $serviceName): bool
    {
        $activeStatuses = [
            CaseModel::STATUS_PENDING,
            CaseModel::STATUS_ACCEPTED,
            CaseModel::STATUS_IN_PROGRESS,
        ];

        return CaseModel::query()
            ->where('lab_id', $labId)
            ->whereIn('status', $activeStatuses)
            ->where('case_type', $serviceName)
            ->exists();
    }
}
