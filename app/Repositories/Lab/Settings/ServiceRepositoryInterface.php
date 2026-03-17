<?php

namespace App\Repositories\Lab\Settings;

use App\Models\LabService;
use Illuminate\Support\Collection;

interface ServiceRepositoryInterface
{
    public function listByLab(int $labId): Collection;

    public function findByLabAndId(int $labId, int $serviceId): ?LabService;

    public function create(array $data): LabService;

    public function update(LabService $service, array $data): LabService;

    public function delete(LabService $service): void;

    public function hasActiveCasesForService(int $labId, string $serviceName): bool;
}
