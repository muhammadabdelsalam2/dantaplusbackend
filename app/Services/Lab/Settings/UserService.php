<?php

namespace App\Services\Lab\Settings;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use App\Http\Resources\Lab\Settings\UserResource;
use App\Repositories\Lab\Settings\UserRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function getLabUsers(array $filters): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        $users = $this->userRepository->paginateByLab($labId, $perPage);

        return ServiceResult::success([
            'data' => UserResource::collection($users->items())->resolve(),
            'meta' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 'Users fetched successfully');
    }

    public function createUser(array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $role = LabRole::from($data['role']);
        $commissionRates = $role === LabRole::LabTechnician
            ? ($data['commission_rates'] ?? null)
            : null;

        $username = $this->generateUsername($labId, $data['full_name']);

        $user = $this->userRepository->create([
            'name' => $data['full_name'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'lab_id' => $labId,
            'status' => UserStatus::Active->value,
            'role' => $role->value,
            'commission_rates' => $commissionRates,
            'is_active' => true,
        ]);

        if (!$user->hasRole('lab')) {
            $user->assignRole('lab');
        }

        return ServiceResult::success(
            (new UserResource($user->fresh()))->resolve(),
            'User created successfully.',
            201
        );
    }

    public function updateUser(int $userId, Request $request): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $user = $this->userRepository->findByLabAndId($labId, $userId);
        if (!$user) {
            return ServiceResult::error('User not found', null, null, 404);
        }

        $data = $request->validated();
        $payload = [];

        if (array_key_exists('full_name', $data)) {
            $payload['name'] = $data['full_name'];
        }

        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'];
        }

        if (array_key_exists('role', $data)) {
            $payload['role'] = $data['role'];
        }

        if ($request->hasFile('avatar_url')) {
            $path = $request->file('avatar_url')->store('avatars/users', 'public');
            $payload['avatar_url'] = asset('storage/' . $path);
        }

        $effectiveRole = array_key_exists('role', $data)
            ? $data['role']
            : $user->role?->value;

        if ($effectiveRole !== LabRole::LabTechnician->value) {
            $payload['commission_rates'] = null;
        } elseif (array_key_exists('commission_rates', $data)) {
            $payload['commission_rates'] = $data['commission_rates'];
        }

        $updated = !empty($payload)
            ? $this->userRepository->update($user, $payload)
            : $user->refresh();

        return ServiceResult::success(
            (new UserResource($updated))->resolve(),
            'User updated successfully.'
        );
    }

    public function updateStatus(int $userId, array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $user = $this->userRepository->findByLabAndId($labId, $userId);
        if (!$user) {
            return ServiceResult::error('User not found', null, null, 404);
        }

        $status = $data['status'];

        if ($user->role?->value === LabRole::LabAdmin->value && $status === UserStatus::Inactive->value) {
            $activeAdmins = $this->userRepository->countActiveLabAdmins($labId, $user->id);
            if ($activeAdmins <= 0) {
                return ServiceResult::error('Cannot deactivate the only active lab admin.', null, null, 422);
            }
        }

        $this->userRepository->update($user, [
            'status' => $status,
            'is_active' => $status === UserStatus::Active->value,
        ]);

        return ServiceResult::success([], 'User status updated.');
    }

    private function generateUsername(int $labId, string $fullName): string
    {
        $base = Str::lower(preg_replace('/\s+/', '', trim($fullName)));
        $base = preg_replace('/[^a-z0-9_\.]/', '', (string) $base);
        $base = $base ?: 'user';

        $username = $base;
        $suffix = 1;

        while ($this->userRepository->usernameExists($labId, $username)) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
