<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Material\IndexMaterialProductRequest;
use App\Http\Requests\Owner\Material\StoreMaterialProductRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialProductRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialProductStatusRequest;
use App\Services\Owner\MaterialProductService;
use App\Support\ApiResponse;

class MaterialProductController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialProductService $materialProductService)
    {
    }

    public function index(IndexMaterialProductRequest $request, int $company)
    {
        $result = $this->materialProductService->indexByCompany($company, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreMaterialProductRequest $request, int $company)
    {
        $result = $this->materialProductService->store($company, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateMaterialProductRequest $request, int $product)
    {
        $result = $this->materialProductService->update($product, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateMaterialProductStatusRequest $request, int $product)
    {
        $result = $this->materialProductService->updateStatus($product, $request->validated('status'));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $product)
    {
        $result = $this->materialProductService->destroy($product);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function categories()
    {
        return ApiResponse::success([
            'company_categories' => config('material_market.company_category_items', []),
            'product_categories' => config('material_market.product_category_items', []),
        ], 'Material categories fetched successfully', 200);
    }
}
