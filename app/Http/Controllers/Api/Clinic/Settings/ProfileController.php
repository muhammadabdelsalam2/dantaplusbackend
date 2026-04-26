<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\UpdateProfilePasswordRequest;
use App\Http\Requests\Clinic\Settings\UpdateProfileRequest;
use App\Services\Clinic\Settings\ProfileService;
use App\Support\ApiResponse;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private ProfileService $service)
    {
    }

    public function show()
    {
        $result = $this->service->show();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateProfileRequest $request)
    {
        $result = $this->service->update($request->validated(), $request->file('avatar'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updatePassword(UpdateProfilePasswordRequest $request)
    {
        $result = $this->service->updatePassword($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
