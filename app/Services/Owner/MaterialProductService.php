<?php

namespace App\Services\Owner;

use App\Repositories\MaterialCompanyRepository;
use App\Repositories\MaterialProductRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaterialProductService
{
    public function __construct(
        private MaterialProductRepository $materialProductRepository,
        private MaterialCompanyRepository $materialCompanyRepository
    ) {
    }

    public function indexByCompany(int $companyId, array $filters): array
    {
        $company = $this->materialCompanyRepository->findById($companyId);
        if (!$company) {
            return ServiceResult::error('Material company not found', null, null, 404);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $products = $this->materialProductRepository->paginateByCompany($companyId, $filters, $perPage);

        $data = [
            'items' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ];

        return ServiceResult::success($data, 'Material products fetched successfully');
    }

    public function store(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data) {
            $company = $this->materialCompanyRepository->findById($companyId);
            if (!$company) {
                return ServiceResult::error('Material company not found', null, null, 404);
            }

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $path = Storage::disk('public')->putFile('material/products', $data['image']);
                $data['image_url'] = Storage::url($path);
            }

            unset($data['image']);

            $product = $this->materialProductRepository->create([
                'company_id' => $companyId,
                'image_url' => $data['image_url'] ?? null,
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'price' => $data['price'],
                'stock' => (int) ($data['stock'] ?? 0),
                'status' => $data['status'] ?? 'Active',
            ]);

            return ServiceResult::success($product, 'Material product created successfully', 201);
        });
    }

    public function update(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            $product = $this->materialProductRepository->findById($productId);
            if (!$product) {
                return ServiceResult::error('Material product not found', null, null, 404);
            }

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $this->deletePublicFileByUrl($product->image_url);
                $path = Storage::disk('public')->putFile('material/products', $data['image']);
                $data['image_url'] = Storage::url($path);
            }

            unset($data['image']);

            $updated = $this->materialProductRepository->update($product, $data);

            return ServiceResult::success($updated, 'Material product updated successfully');
        });
    }

    public function updateStatus(int $productId, string $status): array
    {
        $product = $this->materialProductRepository->findById($productId);
        if (!$product) {
            return ServiceResult::error('Material product not found', null, null, 404);
        }

        $updated = $this->materialProductRepository->update($product, ['status' => $status]);

        return ServiceResult::success($updated, 'Material product status updated successfully');
    }

    public function destroy(int $productId): array
    {
        $product = $this->materialProductRepository->findById($productId);
        if (!$product) {
            return ServiceResult::error('Material product not found', null, null, 404);
        }

        $this->deletePublicFileByUrl($product->image_url);
        $this->materialProductRepository->delete($product);

        return ServiceResult::success(null, 'Material product deleted successfully');
    }

    private function deletePublicFileByUrl(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $path = Str::replaceFirst('/storage/', '', $path);
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
