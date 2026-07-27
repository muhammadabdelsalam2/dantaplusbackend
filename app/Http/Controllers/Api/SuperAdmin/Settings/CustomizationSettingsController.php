<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateCustomizationSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class CustomizationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->customizationSettings());
    }

    public function update(UpdateCustomizationSettingsRequest $request)
    {
        $this->settingsService->updateGroup('customization', $request->validated());

        return ApiResponse::success(
            $this->settingsService->customizationSettings(),
            'Customization settings updated'
        );
    }
}
