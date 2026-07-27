<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateUserManagementSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class UserManagementSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->userManagementSettings());
    }

    public function update(UpdateUserManagementSettingsRequest $request)
    {
        $this->settingsService->updateGroup('user_management', $request->validated());

        return ApiResponse::success(
            $this->settingsService->userManagementSettings(),
            'User management settings updated'
        );
    }
}
