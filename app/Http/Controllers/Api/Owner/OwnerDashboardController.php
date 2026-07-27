<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerDashboardService;
use App\Support\ApiResponse;

class OwnerDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private OwnerDashboardService $service)
    {
    }

    public function dashboard()
    {
        return ApiResponse::success($this->service->dashboard(), 'Dashboard fetched successfully');
    }

    public function analytics()
    {
        return ApiResponse::success($this->service->analytics(), 'Analytics fetched successfully');
    }
}
