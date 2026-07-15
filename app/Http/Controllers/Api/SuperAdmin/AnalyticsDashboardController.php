<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\AnalyticsDashboardService;
use App\Support\ApiResponse;

class AnalyticsDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsDashboardService $service)
    {
    }

    public function index()
    {
        $result = $this->service->dashboard();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
