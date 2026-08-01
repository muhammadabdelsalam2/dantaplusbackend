<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Services\Clinic\DashboardService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
    }

    public function cards()
    {
        return $this->respond($this->service->cards());
    }

    public function revenueTracking()
    {
        return $this->respond($this->service->revenueTracking());
    }

    public function servicesDistribution()
    {
        return $this->respond($this->service->servicesDistribution());
    }

    public function marketingLeadAttribution()
    {
        return $this->respond($this->service->marketingLeadAttribution());
    }

    private function respond(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
