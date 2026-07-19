<?php

namespace App\Services\Company;

use App\Http\Resources\Company\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
public function paginate(array $filters): array
{
    $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
    $products = Product::query()
        ->with(['categoryRelation:id,name', 'company:id,name', 'images'])
        // ← اشيل السطر ده تماماً
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
        $product->load(['categoryRelation:id,name', 'company:id,name', 'images']);
        return (new ProductResource($product))->resolve();
    }

   public function create(array $data): array
{
    return DB::transaction(function () use ($data) {
        $duplicate = Product::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('created_by', auth()->id())
            ->where('name', $data['name'])
            ->where('price', $data['price'])
            ->where('created_at', '>=', now()->subSeconds(5))
            ->latest('id')
            ->first();

        if ($duplicate) {
            return $this->show($duplicate);
        }

        $category = Category::findOrFail($data['category_id']);
        $images = $data['images'] ?? [];
        if (($data['image'] ?? null) instanceof UploadedFile) {
            array_unshift($images, $data['image']);
        }

        $data['image_path']       = $this->storeImage($images[0] ?? null, 'company/products');
        $data['company_id']       = auth()->user()->company_id;
        $data['created_by']       = auth()->id();
        $data['updated_by']       = auth()->id();
        $data['category']         = $category->name;
        $data['approval_status']  = 'pending'; // ← دايماً pending عند الإنشاء
        unset($data['image'], $data['images']);

        $product = Product::create($data);

        if ($product->image_path) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $product->image_path,
                'is_primary' => true,
            ]);
        }

        foreach (array_slice($images, 1) as $image) {
            if ($image instanceof UploadedFile) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $this->storeImage($image, 'company/products'),
                    'is_primary' => false,
                ]);
            }
        }

        return $this->show($product);
    });
}

    public function update(Product $product, array $data): array
    {
        return DB::transaction(function () use ($product, $data) {
            if (isset($data['category_id'])) {
                $data['category'] = Category::findOrFail($data['category_id'])->name;
            }

            $images = $data['images'] ?? [];
            if (($data['image'] ?? null) instanceof UploadedFile) {
                array_unshift($images, $data['image']);
            }

            $data['updated_by'] = auth()->id();
            unset($data['image'], $data['images']);
            $product->update($data);

            foreach ($images as $image) {
                if (! $image instanceof UploadedFile) {
                    continue;
                }

                $path = $this->storeImage($image, 'company/products');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => ! $product->images()->exists(),
                ]);

                if (! $product->image_path) {
                    $product->update(['image_path' => $path]);
                }
            }

            return $this->show($product->fresh());
        });
    }

    public function delete(Product $product): void
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();
    }

    public function deleteImage(Product $product, int $imageId): void
    {
        $image = $product->images()->where('id', $imageId)->firstOrFail();
        Storage::disk('public')->delete($image->image_path);
        $wasPrimary = $image->is_primary || $product->image_path === $image->image_path;
        $image->delete();

        if ($wasPrimary) {
            $replacement = $product->images()->oldest('id')->first();
            $product->update(['image_path' => $replacement?->image_path]);
            if ($replacement) {
                $replacement->update(['is_primary' => true]);
            }
        }
    }

    private function storeImage(?UploadedFile $image, string $dir): ?string
    {
        return $image ? $image->store($dir, 'public') : null;
    }
}
