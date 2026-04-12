<?php

namespace App\Services\SuperAdmin;

use App\Enums\LabRole;
use App\Models\DentalLab;
use App\Models\User;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Support\UserRoleManager;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private UserManagementRepositoryInterface $repo
    ) {}

    public function list(?string $q, ?string $role, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->repo->paginateUsers($q, $role, $status, $perPage);
    }

    public function show(int $id): User
    {
        return $this->repo->findUserOrFail($id);
    }

    /**
     * @throws ValidationException
     */
    public function create(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $role = UserRoleManager::normalize($data['role'] ?? null);

            if (!$role) {
                throw ValidationException::withMessages([
                    'role' => ['Role is required.'],
                ]);
            }

            UserRoleManager::ensureRoleExists($role);

            // ✅ منع إعطاء super-admin إلا بواسطة super-admin
            if ($role === 'super-admin' && (!$actor || !$actor->isSuperAdmin())) {
                throw ValidationException::withMessages([
                    'role' => ['Only super-admin can assign super-admin role.'],
                ]);
            }

            $data = Arr::except($data, ['role', 'lab_name']);
            $data['role'] = UserRoleManager::isLabScopedRole($role) ? $role : null;
            $data['lab_id'] = $this->resolveLabIdForCreate($role, $data, $actor);

            $data['password'] = Hash::make($data['password']);

            $user = $this->repo->createUser($data);
            $user->syncRoles([$role]);

            return $this->repo->findUserOrFail($user->id);
        });
    }

    /**
     * @throws ValidationException
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $role = isset($data['role'])
                ? UserRoleManager::normalize($data['role'])
                : null;
            $data = Arr::except($data, ['role', 'lab_name']);

            // ✅ ممنوع تغيير role لسوبر أدمن
            if ($user->isSuperAdmin() && $role !== null) {
                throw ValidationException::withMessages([
                    'role' => ['Cannot change role for super-admin.'],
                ]);
            }

            // ✅ منع إعطاء super-admin إلا بواسطة super-admin
            if ($role === 'super-admin' && (!$actor || !$actor->isSuperAdmin())) {
                throw ValidationException::withMessages([
                    'role' => ['Only super-admin can assign super-admin role.'],
                ]);
            }

            if ($role !== null) {
                UserRoleManager::ensureRoleExists($role);
            }

            // ✅ ممنوع تعطيل نفسك
            if (array_key_exists('is_active', $data) && (int)$data['is_active'] === 0 && $actor && $actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'is_active' => ['You cannot deactivate your own account.'],
                ]);
            }

            if (array_key_exists('password', $data)) {
                if (!empty($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                } else {
                    unset($data['password']);
                }
            }

            if ($role !== null) {
                $data['role'] = UserRoleManager::isLabScopedRole($role) ? $role : null;

                if (UserRoleManager::isLabScopedRole($role)) {
                    $data['lab_id'] = $this->resolveLabIdForUpdate($user, $role, $data);
                } elseif ($user->lab_id !== null) {
                    $data['lab_id'] = null;
                }
            }

            $updated = $this->repo->updateUser($user, $data);

            if ($role) {
                $updated->syncRoles([$role]);
            }

            return $this->repo->findUserOrFail($updated->id);
        });
    }

    /**
     * @throws ValidationException
     */
    public function toggleStatus(User $user, ?User $actor = null): User
    {
        // ✅ ممنوع تعطيل نفسك
        if ($actor && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        // ✅ ممنوع تعطيل آخر super-admin
        if ($user->isSuperAdmin()) {
            $superAdminsCount = User::role('super-admin')->count();
            if ($superAdminsCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['Cannot deactivate the last super-admin.'],
                ]);
            }
        }

        $this->repo->updateUser($user, [
            'is_active' => $user->is_active ? 0 : 1,
        ]);

        return $this->repo->findUserOrFail($user->id);
    }

    /**
     * @throws ValidationException
     */
    public function delete(User $user, ?User $actor = null): void
    {
        // ✅ ممنوع حذف نفسك
        if ($actor && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        // ✅ ممنوع حذف آخر super-admin
        if ($user->isSuperAdmin()) {
            $superAdminsCount = User::role('super-admin')->count();
            if ($superAdminsCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['Cannot delete the last super-admin.'],
                ]);
            }
        }

        $this->repo->deleteUser($user);
    }

    /**
     * @throws ValidationException
     */
    private function resolveLabIdForCreate(string $role, array $data, ?User $actor): ?int
    {
        if (! UserRoleManager::isLabScopedRole($role)) {
            return null;
        }

        if (! $actor?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin can create lab-scoped users from this endpoint.'],
            ]);
        }

        if (! empty($data['lab_id'])) {
            return (int) $data['lab_id'];
        }

        if ($role !== LabRole::LabAdmin->value) {
            throw ValidationException::withMessages([
                'lab_id' => ['lab_id is required for this role.'],
            ]);
        }

        return $this->createLabForAdmin($data)->id;
    }

    /**
     * @throws ValidationException
     */
    private function resolveLabIdForUpdate(User $user, string $role, array $data): ?int
    {
        if (! empty($data['lab_id'])) {
            return (int) $data['lab_id'];
        }

        if ($user->lab_id) {
            return $user->lab_id;
        }

        if ($role !== LabRole::LabAdmin->value) {
            throw ValidationException::withMessages([
                'lab_id' => ['lab_id is required for this role.'],
            ]);
        }

        return $this->createLabForAdmin($data, $user)->id;
    }

    private function createLabForAdmin(array $data, ?User $user = null): DentalLab
    {
        $labName = trim((string) ($data['lab_name'] ?? $data['name'] ?? $user?->name ?? 'New Lab'));
        $labEmail = $data['email'] ?? $user?->email;

        return DentalLab::query()->create([
            'name' => $labName,
            'contact_person' => $data['name'] ?? $user?->name,
            'email' => $labEmail,
            'phone' => $data['phone'] ?? $user?->phone,
            'status' => DentalLab::STATUS_ACTIVE,
            'avg_delivery_days' => 0,
            'date_added' => now()->toDateString(),
        ]);
    }
}
