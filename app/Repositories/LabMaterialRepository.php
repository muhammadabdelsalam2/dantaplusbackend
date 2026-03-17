<?php

namespace App\Repositories;

use App\Models\LabMaterial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LabMaterialRepository
{
    public function paginateForLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = LabMaterial::query()
            ->where('lab_id', $labId)
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                });
            })
            ->when($filters['supplier'] ?? null, fn (Builder $query, $supplier) => $query->where('supplier', 'like', "%{$supplier}%"))
            ->when($filters['low_stock'] ?? null, function (Builder $query, $lowStock) {
                if ($lowStock) {
                    $query->whereColumn('stock', '<=', 'low_stock_threshold');
                }
            })
            ->when($filters['expiring_before'] ?? null, function (Builder $query, $expiringBefore) {
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '<=', $expiringBefore);
            })
            ->when($filters['purchase_date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('purchase_date', '>=', $from))
            ->when($filters['purchase_date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('purchase_date', '<=', $to));

        $sortBy = $filters['sort_by'] ?? null;
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderByDesc('id');
        }

        return $query->paginate($perPage);
    }

    public function findForLabById(int $labId, int $materialId): ?LabMaterial
    {
        return LabMaterial::query()
            ->where('lab_id', $labId)
            ->find($materialId);
    }

    public function create(array $data): LabMaterial
    {
        return LabMaterial::query()->create($data);
    }

    public function update(LabMaterial $material, array $data): LabMaterial
    {
        $material->update($data);

        return $material->refresh();
    }

    public function delete(LabMaterial $material): void
    {
        $material->delete();
    }
}
