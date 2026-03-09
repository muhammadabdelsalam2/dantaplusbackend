<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Alerts\IndexRenewalAlertsRequest;
use App\Http\Requests\Owner\Alerts\StoreRenewalReminderRequest;
use App\Services\Owner\RenewalAlertsService;
use App\Support\ApiResponse;

class RenewalAlertsController extends Controller
{
    use ApiResponse;

    public function __construct(private RenewalAlertsService $service) {}

    public function index(IndexRenewalAlertsRequest $request)
    {
        $result = $this->service->listRenewalAlerts($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function sendReminder(StoreRenewalReminderRequest $request)
    {
        $result = $this->service->sendReminder($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
