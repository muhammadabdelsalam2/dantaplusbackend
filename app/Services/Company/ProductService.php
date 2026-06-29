<?php

namespace App\Services\Company;

use App\Http\Resources\Company\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
 public function paginate(array $filters): array
{
    $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
    $products = Product::query()
        ->with(['categoryRelation:id,name', 'company:id,name'])
        ->where('approval_status', Product::APPROVAL_APPROVED) // ← أضف السطر ده
        ->when($filters['name'] ?? null, fn ($q, $name) => $q->where('name', 'like', "%{$name}%"))
        ->when($filters['category_id'] ?? null, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
        ->when($filters['category'] ?? null, fn ($q, $category) => $q->whereHas(
            'categoryRelation',
            fn ($q) => $q->where('name', $category)
        ))
        ->when($filters['min_price'] ?? null, fn ($q, $minPrice) => $q->where('price', '>=', $minPrice))
        ->when($filters['max_price'] ?? null, fn ($q, $maxPrice) => $q->where('price', '<=', $maxPrice))
        ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
        ->latest('id')
        ->paginate($perPage);

    return [
        'items' => ProductResource::collection($products->items())->resolve(),
        'meta' => ['page' => $products->currentPage(), 'per_page' => $products->perPage(), 'total' => $products->total()],
    ];
}

    public function show(Product $product): array
    {
        $product->load(['categoryRelation:id,name', 'company:id,name']);
        return (new ProductResource($product))->resolve();
    }

   public function create(array $data): array
{
    return DB::transaction(function () use ($data) {
        $category = Category::findOrFail($data['category_id']);
        $data['image_path']       = $this->storeImage($data['image'] ?? null, 'company/products');
        $data['company_id']       = auth()->user()->company_id;
        $data['created_by']       = auth()->id();
        $data['updated_by']       = auth()->id();
        $data['category']         = $category->name;
        $data['approval_status']  = 'pending'; // ← دايماً pending عند الإنشاء
        unset($data['image']);

        $product = Product::create($data);
        return $this->show($product);
    });
}

    public function update(Product $product, array $data): array
    {
        return DB::transaction(function () use ($product, $data) {
            if (isset($data['category_id'])) {
                $data['category'] = Category::findOrFail($data['category_id'])->name;
            }

            if (($data['image'] ?? null) instanceof UploadedFile) {
                if ($product->image_path) {
                    Storage::disk('public')->delete($product->image_path);
                }
                $data['image_path'] = $this->storeImage($data['image'], 'company/products');
            }

            $data['updated_by'] = auth()->id();
            unset($data['image']);
            $product->update($data);
            return $this->show($product->fresh());
        });
    }

    public function delete(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();
    }

    private function storeImage(?UploadedFile $image, string $dir): ?string
    {
        return $image ? $image->store($dir, 'public') : null;
    }
}
