<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\ReportService;
use App\Support\ApiResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(private ReportService $service) {}

    public function ordersByMonth() { return ApiResponse::success($this->service->ordersByMonth(), 'Orders by month fetched successfully'); }
    public function revenueByClinic() { return ApiResponse::success($this->service->revenueByClinic(), 'Revenue by clinic fetched successfully'); }
    public function mostRequestedMaterials() { return ApiResponse::success($this->service->mostRequestedMaterials(), 'Most requested materials fetched successfully'); }
}
