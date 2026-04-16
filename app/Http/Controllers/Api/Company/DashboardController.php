<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\DashboardService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private DashboardService $service) {}

    public function index()
    {
        return ApiResponse::success($this->service->overview(), 'Dashboard fetched successfully');
    }

    public function orderTrends()
    {
        return ApiResponse::success($this->service->orderTrends(), 'Order trends fetched successfully');
    }
}
