<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Material\IndexMaterialOrderRequest;
use App\Http\Requests\Owner\Material\ShowMaterialOrderRequest;
use App\Services\Owner\MaterialOrderService;
use App\Support\ApiResponse;

class MaterialOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialOrderService $materialOrderService)
    {
    }

    public function index(IndexMaterialOrderRequest $request)
    {
        $result = $this->materialOrderService->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(ShowMaterialOrderRequest $request, int $order)
    {
        $result = $this->materialOrderService->show($order);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
