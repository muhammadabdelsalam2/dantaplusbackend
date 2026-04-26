<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\BranchResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Str;

class BranchService
{
    public function __construct(private ClinicSettingsRepositoryInterface $repository)
    {
    }

    public function index(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            [
                'items' => BranchResource::collection($this->repository->listBranches($clinicId))->resolve(),
                'managers' => $this->repository->listManagers($clinicId),
            ],
            'Branches fetched successfully'
        );
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        if (! $this->managerBelongsToClinic($data['manager_id'] ?? null, $clinicId)) {
            return ServiceResult::error('Selected manager does not belong to this clinic.', null, ['manager_id' => ['Selected manager does not belong to this clinic.']], 422);
        }

        $branch = $this->repository->createBranch([
            'clinic_id' => $clinicId,
            'name' => $data['name'],
            'code' => Str::upper(Str::slug($data['name'] . '-' . now()->format('His'), '-')),
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'working_hours_from' => $data['working_hours_from'] ?? null,
            'working_hours_to' => $data['working_hours_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'rooms_count' => $data['rooms_count'],
            'status' => $data['status'] ?? 'Active',
        ]);

        $branch = $this->repository->findBranchForClinic($clinicId, $branch->id);

        return ServiceResult::success((new BranchResource($branch))->resolve(), 'Branch created successfully', 201);
    }

    public function show(int $id): array
    {
        $branch = $this->resolveBranch($id);
        if (! $branch) {
            return ServiceResult::error('Branch not found.', null, null, 404);
        }

        return ServiceResult::success((new BranchResource($branch))->resolve(), 'Branch fetched successfully');
    }

    public function update(int $id, array $data): array
    {
        $branch = $this->resolveBranch($id);
        if (! $branch) {
            return ServiceResult::error('Branch not found.', null, null, 404);
        }

        if (! $this->managerBelongsToClinic($data['manager_id'] ?? null, $branch->clinic_id)) {
            return ServiceResult::error('Selected manager does not belong to this clinic.', null, ['manager_id' => ['Selected manager does not belong to this clinic.']], 422);
        }

        $updated = $this->repository->updateBranch($branch, $data);

        return ServiceResult::success((new BranchResource($updated))->resolve(), 'Branch updated successfully');
    }

    public function destroy(int $id): array
    {
        $branch = $this->resolveBranch($id);
        if (! $branch) {
            return ServiceResult::error('Branch not found.', null, null, 404);
        }

        $this->repository->deleteBranch($branch);

        return ServiceResult::success(null, 'Branch deleted successfully');
    }

    private function resolveBranch(int $id)
    {
        $clinicId = $this->currentClinicId();

        return $clinicId ? $this->repository->findBranchForClinic($clinicId, $id) : null;
    }

    private function managerBelongsToClinic(?int $managerId, int $clinicId): bool
    {
        if (! $managerId) {
            return true;
        }

        return $this->repository->listManagers($clinicId)->contains('id', $managerId);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
