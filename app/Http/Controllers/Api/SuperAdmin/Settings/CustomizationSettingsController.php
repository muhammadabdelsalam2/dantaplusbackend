<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateCustomizationSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class CustomizationSettingsController extends Controller
{
    use ApiResponse;

    private const GROUP = 'customization';

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->getGroup(self::GROUP));
    }

    public function update(UpdateCustomizationSettingsRequest $request)
    {
        $data = $this->settingsService->updateGroup(self::GROUP, $request->validated());
        return ApiResponse::success($data, 'Customization settings updated');
    }
}
