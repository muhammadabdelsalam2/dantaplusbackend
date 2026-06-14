<?php

namespace App\Repositories;

use App\Models\MaterialProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MaterialProductRepository
{
    public function paginateByCompany(int $companyId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MaterialProduct::query()
            ->where('company_id', $companyId)

            ->when($filters['brand'] ?? null, fn ($q, $brand) =>
                $q->where('brand', 'like', "%{$brand}%")
            )

            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                       ->orWhere('brand', 'like', "%{$search}%")
                       ->orWhere('category', 'like', "%{$search}%");
                });
            })

          ->when($filters['category'] ?? null, fn ($q, $category) =>
    $q->where('category', 'like', "%{$category}%")
)

            ->when($filters['status'] ?? null, fn ($q, $status) =>
                $q->whereIn('status', [ucfirst($status), strtolower($status)])
            )

            ->when($filters['min_price'] ?? null, fn ($q, $min) =>
                $q->where('price', '>=', $min)
            )

            ->when($filters['max_price'] ?? null, fn ($q, $max) =>
                $q->where('price', '<=', $max)
            )

            ->orderByDesc('id')
            ->paginate($perPage);
    }

public function paginateCatalog(array $filters, int $perPage = 15): LengthAwarePaginator
{
    return MaterialProduct::query()
        ->with(['company:id,name,status'])



        ->when($filters['brand'] ?? null, fn ($q, $brand) =>
            $q->where('brand', 'like', "%{$brand}%")
        )

        ->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('brand', 'like', "%{$search}%")
                   ->orWhere('category', 'like', "%{$search}%")
                   ->orWhere('barcode', 'like', "%{$search}%");
            });
        })

        ->when($filters['category'] ?? null, fn ($q, $category) =>
            $q->where('category', $category)
        )

        ->when($filters['min_price'] ?? null, fn ($q, $min) =>
            $q->where('price', '>=', $min)
        )

        ->when($filters['max_price'] ?? null, fn ($q, $max) =>
            $q->where('price', '<=', $max)
        )

        ->orderByDesc('id')
        ->paginate($perPage);
}

    public function findById(int $productId, array $with = []): ?MaterialProduct
    {
        return MaterialProduct::with($with)->find($productId);
    }

    public function findCatalogById(int $productId): ?MaterialProduct
    {
        return MaterialProduct::query()
            ->with(['company:id,name,status'])
            ->whereIn('status', ['Active', 'active'])
            ->whereHas('company', fn ($q) =>
                $q->whereIn('status', ['Active', 'active'])
            )
            ->find($productId);
    }

    public function findCatalogByBarcode(string $barcode): ?MaterialProduct
    {
        return MaterialProduct::query()
            ->with(['company:id,name,status'])
            ->whereIn('status', ['Active', 'active'])
            ->whereHas('company', fn ($q) =>
                $q->whereIn('status', ['Active', 'active'])
            )
            ->where('barcode', $barcode)
            ->first();
    }

    public function catalogFilters(): array
    {
        $brands = DB::table('material_products')
            ->join('material_companies', 'material_companies.id', '=', 'material_products.company_id')
            ->whereIn('material_products.status', ['Active', 'active'])
            ->whereIn('material_companies.status', ['Active', 'active'])
            ->whereNotNull('material_products.brand')
            ->distinct()
            ->orderBy('material_products.brand')
            ->pluck('material_products.brand')
            ->values();

        $price = DB::table('material_products')
            ->join('material_companies', 'material_companies.id', '=', 'material_products.company_id')
            ->whereIn('material_products.status', ['Active', 'active'])
            ->whereIn('material_companies.status', ['Active', 'active'])
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return [
            'categories' => config('material_market.product_category_items', []),
            'brands' => $brands,
            'price_range' => [
                'min' => (float) ($price->min_price ?? 0),
                'max' => (float) ($price->max_price ?? 0),
            ],
        ];
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
