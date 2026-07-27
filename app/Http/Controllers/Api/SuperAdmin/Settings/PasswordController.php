<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\ChangePasswordRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class PasswordController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function update(ChangePasswordRequest $request)
    {
        $validated = $request->validated();

        $this->settingsService->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['new_password'] ?? $validated['password'],
        );

        return ApiResponse::success(null, 'Password updated');
    }
}
