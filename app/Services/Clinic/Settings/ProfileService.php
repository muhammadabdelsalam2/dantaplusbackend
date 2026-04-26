<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\ProfileResource;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function show(): array
    {
        $user = auth()->user();

        if (! $user || ! $user->clinic_id) {
            return ServiceResult::error('Clinic user not found.', null, null, 403);
        }

        return ServiceResult::success((new ProfileResource($user))->resolve(), 'Profile fetched successfully');
    }

public function update(array $data, $avatar = null): array
{
    $user = auth()->user();

    if (! $user || ! $user->clinic_id) {
        return ServiceResult::error('Clinic user not found.', null, null, 403);
    }

    $payload = array_filter([
        'name' => $data['name'] ?? null,
        'username' => $data['username'] ?? null,
    ]);

    if ($avatar) {
        $payload['avatar_url'] = asset('storage/' . $avatar->store('avatars/users', 'public'));
    }

    $user->update($payload);

    return ServiceResult::success(
        (new ProfileResource($user->fresh()))->resolve(),
        'Profile updated successfully'
    );
}

    public function updatePassword(array $data): array
    {
        $user = auth()->user();

        if (! $user || ! $user->clinic_id) {
            return ServiceResult::error('Clinic user not found.', null, null, 403);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            return ServiceResult::error('Current password is incorrect.', null, ['current_password' => ['Current password is incorrect.']], 422);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        return ServiceResult::success(null, 'Password updated successfully');
    }
}
