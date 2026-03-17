<?php

namespace App\Services\Lab;

use App\Http\Resources\Lab\Material\LabMaterialResource;
use App\Repositories\LabMaterialRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class LabMaterialService
{
    public function __construct(private LabMaterialRepository $materialRepository)
    {
    }

    public function listMaterials(array $filters): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $materials = $this->materialRepository->paginateForLab($labId, $filters, $perPage);

        return ServiceResult::success([
            'items' => LabMaterialResource::collection($materials->items())->resolve(),
            'pagination' => [
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
            ],
        ], 'Lab materials fetched successfully');
    }

    public function showMaterial(int $materialId): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $material = $this->materialRepository->findForLabById($labId, $materialId);
        if (! $material) {
            return ServiceResult::error('Material not found', null, null, 404);
        }

        return ServiceResult::success((new LabMaterialResource($material))->resolve(), 'Material fetched successfully');
    }

    public function createMaterial(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        if (! empty($data['lab_id']) && (int) $data['lab_id'] !== (int) $labId) {
            return ServiceResult::error('Lab mismatch for this account', null, null, 403);
        }

        return DB::transaction(function () use ($data, $labId) {
            $material = $this->materialRepository->create([
                'lab_id' => $labId,
                'name' => $data['name'],
                'supplier' => $data['supplier'],
                'stock' => $data['stock'],
                'low_stock_threshold' => $data['low_stock_threshold'],
                'cost' => $data['cost'],
                'purchase_date' => $data['purchase_date'],
                'expiration_date' => $data['expiration_date'] ?? null,
            ]);

            return ServiceResult::success(
                (new LabMaterialResource($material))->resolve(),
                'Material created successfully',
                201
            );
        });
    }

    public function updateMaterial(int $materialId, array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        return DB::transaction(function () use ($materialId, $data, $labId) {
            $material = $this->materialRepository->findForLabById($labId, $materialId);
            if (! $material) {
                return ServiceResult::error('Material not found', null, null, 404);
            }

            $updated = $this->materialRepository->update($material, $data);

            return ServiceResult::success(
                (new LabMaterialResource($updated))->resolve(),
                'Material updated successfully'
            );
        });
    }

    public function deleteMaterial(int $materialId): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $material = $this->materialRepository->findForLabById($labId, $materialId);
        if (! $material) {
            return ServiceResult::error('Material not found', null, null, 404);
        }

        $this->materialRepository->delete($material);

        return ServiceResult::success(null, 'Material deleted successfully');
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
