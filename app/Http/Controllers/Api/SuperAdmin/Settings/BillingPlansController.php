<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateBillingPlansRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class BillingPlansController extends Controller
{
    use ApiResponse;

    private const GROUP = 'billing_plans';

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        // default plan structure if empty
        $data = $this->settingsService->getGroup(self::GROUP);

        return ApiResponse::success([
            'basic' => $data['basic'] ?? null,
            'standard' => $data['standard'] ?? null,
            'premium' => $data['premium'] ?? null,
        ]);
    }

    public function update(UpdateBillingPlansRequest $request)
    {
        $data = $this->settingsService->updateGroup(self::GROUP, $request->validated());
        return ApiResponse::success($data, 'Billing plans updated');
    }
}
