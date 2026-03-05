<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateProfileRequest;
use App\Http\Requests\SuperAdmin\Settings\UploadProfilePhotoRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'suggested_username' => $this->settingsService->getSuggestedUsername($user),
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'is_active' => (bool)($user->is_active ?? true),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $this->settingsService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'suggested_username' => $this->settingsService->getSuggestedUsername($user),
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'is_active' => (bool)($user->is_active ?? true),
        ], 'Profile updated');
    }

    public function uploadPhoto(UploadProfilePhotoRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $upload = $this->settingsService->uploadPublicFile($request->file('file'), 'profile');
            return ApiResponse::success($upload, 'Photo uploaded');
        }

        if (!empty($data['file_base64'])) {
            $upload = $this->settingsService->uploadFromBase64(
                $data['file_base64'],
                'profile',
                $data['filename'] ?? 'profile.png'
            );
            return ApiResponse::success($upload, 'Photo uploaded');
        }

        return ApiResponse::error('No file provided', 422);
    }
}
