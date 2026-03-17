<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\UpdateLabProfileRequest;
use App\Services\Lab\Settings\LabProfileService;
use App\Support\ApiResponse;

class LabProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private LabProfileService $labProfileService)
    {
    }

    public function show()
    {
        $result = $this->labProfileService->showProfile();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateLabProfileRequest $request)
    {
        $result = $this->labProfileService->updateProfile($request);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
