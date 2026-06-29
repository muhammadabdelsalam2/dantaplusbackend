<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Material\IndexMaterialProductRequest;
use App\Http\Requests\Owner\Material\StoreMaterialProductRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialProductRequest;
use App\Http\Requests\Owner\Material\UpdateMaterialProductStatusRequest;
use App\Services\Owner\MaterialProductService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;


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
    public function pending(Request $request)
{
    $filters = $request->validate([
        'search'   => 'nullable|string|max:100',
        'per_page' => 'nullable|integer|min:1|max:100',
    ]);

    $result = $this->materialProductService->pendingProducts($filters);
    return ApiResponse::success($result['data'], $result['message']);
}

public function approve(int $product)
{
    $result = $this->materialProductService->approveProduct($product);

    if (!$result['success']) {
        return ApiResponse::error($result['message'], $result['code']);
    }

    return ApiResponse::success($result['data'], $result['message']);
}

public function reject(Request $request, int $product)
{
    $request->validate([
        'reason' => 'required|string|max:500',
    ]);

    $result = $this->materialProductService->rejectProduct($product, $request->reason);

    if (!$result['success']) {
        return ApiResponse::error($result['message'], $result['code']);
    }

    return ApiResponse::success($result['data'], $result['message']);
}
}
