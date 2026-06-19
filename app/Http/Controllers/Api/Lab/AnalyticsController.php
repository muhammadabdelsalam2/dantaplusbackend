<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AnalyticsRequest;
use App\Services\Lab\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $service)
    {
    }

    public function overview(AnalyticsRequest $request): JsonResponse
    {
        $result = $this->service->overview($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
