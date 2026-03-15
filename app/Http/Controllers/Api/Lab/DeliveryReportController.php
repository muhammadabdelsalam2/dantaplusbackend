<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\DeliveryReport\IndexDeliveryReportRequest;
use App\Services\Lab\DeliveryReportService;
use App\Support\ApiResponse;

class DeliveryReportController extends Controller
{
    use ApiResponse;

    public function __construct(private DeliveryReportService $service)
    {
    }

    public function index(IndexDeliveryReportRequest $request)
    {
        $result = $this->service->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
