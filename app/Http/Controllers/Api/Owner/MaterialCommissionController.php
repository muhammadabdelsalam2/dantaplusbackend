<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Material\IndexMaterialCommissionRequest;
use App\Services\Owner\MaterialCommissionService;
use App\Support\ApiResponse;

class MaterialCommissionController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialCommissionService $materialCommissionService)
    {
    }

    public function index(IndexMaterialCommissionRequest $request)
    {
        $result = $this->materialCommissionService->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
