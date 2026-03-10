<?php

namespace App\Repositories;

use App\Models\MaterialProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaterialProductRepository
{
    public function paginateByCompany(int $companyId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MaterialProduct::query()
            ->where('company_id', $companyId)
            ->when($filters['brand'] ?? null, function ($query, $brand) {
                $query->where('brand', 'like', "%{$brand}%");
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', strtolower($category)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', strtolower($status)))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $productId, array $with = []): ?MaterialProduct
    {
        return MaterialProduct::with($with)->find($productId);
    }

    public function create(array $data): MaterialProduct
    {
        return MaterialProduct::create($data);
    }

    public function update(MaterialProduct $product, array $data): MaterialProduct
    {
        $product->update($data);

        return $product->refresh();
    }

    public function delete(MaterialProduct $product): void
    {
        $product->delete();
    }
}
