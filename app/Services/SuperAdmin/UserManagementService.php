<?php

namespace App\Services\SuperAdmin;

use App\Mail\SystemAccessMail;
use App\Models\User;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Support\UserRoleManager;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    public function __construct(
        private UserManagementRepositoryInterface $repo
    ) {}

    public function list(?string $q, ?string $role, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->repo->paginateUsers($q, $role, $status, $perPage);
    }

    public function rolesFilter(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->pluck('name')
            ->values()
            ->all();
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

            $entityAssignments = $this->entityAssignmentsForRole($role, $data);
            $data = Arr::except($data, ['role', 'lab_name', 'material_company_id']);
            $data = array_merge($data, $entityAssignments);
            $data['role'] = UserRoleManager::isLabScopedRole($role) ? $role : null;

            $plainPassword = $data['password'];
            $data['password'] = Hash::make($data['password']);

            $user = $this->repo->createUser($data);
            $user->syncRoles([$role]);

            Mail::to($user->email)->send(new SystemAccessMail(
                $user->name,
                config('app.frontend_url'),
                $user->email,
                $plainPassword
            ));

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
            $entityAssignments = $role !== null ? $this->entityAssignmentsForRole($role, $data) : [];
            $data = Arr::except($data, ['role', 'lab_name', 'material_company_id']);

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
                $data = array_merge($data, $entityAssignments);
                $data['role'] = UserRoleManager::isLabScopedRole($role) ? $role : null;
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

    private function entityAssignmentsForRole(string $role, array $data): array
    {
        return match (UserRoleManager::entityTypeForRole($role)) {
            'clinic' => [
                'clinic_id' => (int) $data['clinic_id'],
                'lab_id' => null,
                'company_id' => null,
            ],
            'lab' => [
                'clinic_id' => null,
                'lab_id' => (int) $data['lab_id'],
                'company_id' => null,
            ],
            'material_company' => [
                'clinic_id' => null,
                'lab_id' => null,
                'company_id' => (int) ($data['material_company_id'] ?? $data['company_id']),
            ],
            default => [
                'clinic_id' => null,
                'lab_id' => null,
                'company_id' => null,
            ],
        };
    }

}
