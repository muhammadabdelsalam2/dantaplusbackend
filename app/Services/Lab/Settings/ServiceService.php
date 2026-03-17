<?php

namespace App\Services\Lab\Settings;

use App\Http\Resources\Lab\Settings\ServiceResource;
use App\Repositories\Lab\Settings\ServiceRepositoryInterface;
use App\Support\ServiceResult;

class ServiceService
{
    public function __construct(private ServiceRepositoryInterface $serviceRepository)
    {
    }

    public function listServices(): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $services = $this->serviceRepository->listByLab($labId);

        return ServiceResult::success(ServiceResource::collection($services)->resolve(), 'Services fetched successfully');
    }

    public function createService(array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $service = $this->serviceRepository->create([
            'lab_id' => $labId,
            'service_name' => $data['service_name'],
            'price' => $data['price'],
            'turnaround_time_days' => $data['turnaround_time_days'],
        ]);

        return ServiceResult::success(
            (new ServiceResource($service))->resolve(),
            'Service added successfully.',
            201
        );
    }

    public function updateService(int $serviceId, array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $service = $this->serviceRepository->findByLabAndId($labId, $serviceId);
        if (!$service) {
            return ServiceResult::error('Service not found', null, null, 404);
        }

        $updated = $this->serviceRepository->update($service, $data);

        return ServiceResult::success(
            (new ServiceResource($updated))->resolve(),
            'Service updated successfully.'
        );
    }

    public function deleteService(int $serviceId): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $service = $this->serviceRepository->findByLabAndId($labId, $serviceId);
        if (!$service) {
            return ServiceResult::error('Service not found', null, null, 404);
        }

        if ($this->serviceRepository->hasActiveCasesForService($labId, $service->service_name)) {
            return ServiceResult::error('Cannot delete a service linked to active orders.', null, null, 422);
        }

        $this->serviceRepository->delete($service);

        return ServiceResult::success(null, 'Service deleted.');
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
