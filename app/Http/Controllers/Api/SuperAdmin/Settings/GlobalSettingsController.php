<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateGlobalSettingsRequest;
use App\Support\ApiResponse;
use App\Services\SuperAdmin\SettingsService;

class GlobalSettingsController extends Controller
{
    private const GROUP = 'global';

    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function show()
    {
        return ApiResponse::success(
            $this->settingsService->getGroup(self::GROUP)
        );
    }

    public function update(UpdateGlobalSettingsRequest $request)
    {
        $data = $this->settingsService->updateGroup(
            self::GROUP,
            $request->validated()
        );

        return ApiResponse::success(
            $data,
            'Global settings updated'
        );
    }
}
