<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateBillingPlansRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class BillingPlansController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->billingSettings());
    }

    public function update(UpdateBillingPlansRequest $request)
    {
        $this->settingsService->updateGroup('billing_plans', $request->validated());

        return ApiResponse::success($this->settingsService->billingSettings(), 'Billing plans updated');
    }
}
