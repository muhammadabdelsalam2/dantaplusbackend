<?php

namespace App\Services\Access;

use App\Models\User;
use App\Repositories\Contracts\Access\RoleAccessRepositoryInterface;
use App\Support\ServiceResult;
use App\Support\UserRoleManager;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class RoleAccessService
{
    public function __construct(private RoleAccessRepositoryInterface $repository)
    {
    }

    public function currentUserAccess(User $user): array
    {
        $roleName = UserRoleManager::primaryRole($user);
        $role = $roleName ? $this->repository->findRoleByName($roleName) : null;
        $permissions = $role
            ? $this->repository->permissionNames($role)
            : $this->repository->userPermissionNames($user);

        return ServiceResult::success([
            'name' => $user->name,
            'role' => $roleName,
            'role_id' => $role?->id,
            'permissions' => $permissions,
            'modules' => $this->visibleModulesForRole($roleName, $permissions),
        ], 'User access fetched successfully');
    }

    public function permissionsMatrix(): array
    {
        $roles = $this->repository->rolesWithPermissions()
            ->map(fn (Role $role) => [
                'role_id' => $role->id,
                'role' => $role->name,
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return ServiceResult::success($roles, 'Roles permissions matrix fetched successfully');
    }

    /**
     * @param array<int, string> $modules
     */
    public function syncRolePermissions(User $user, string  $roleId, array $modules): array
    {
        $targetRole = $this->repository->findRoleOrFail($roleId);
        $allowedRoles = $this->manageableRoleNames($user);

        if ($allowedRoles === []) {
            return ServiceResult::error('Only clinic, lab, or supplier admins can update role permissions.', null, null, 403);
        }

        if (! in_array($targetRole->name, $allowedRoles, true)) {
            return ServiceResult::error('You are not allowed to update permissions for this role.', null, null, 403);
        }

        $role = DB::transaction(function () use ($targetRole, $modules) {
            // translate modules -> permissions based on frontend_modules config and role type
            $type = $this->frontendModuleType($targetRole->name);
            $moduleConfig = $type ? config("frontend_modules.{$type}", []) : [];

            $permissionNames = [];
            foreach ($modules as $module) {
                $perms = $moduleConfig[$module] ?? [];
                foreach ($perms as $p) {
                    $permissionNames[] = $p;
                }
            }

            $permissionNames = array_values(array_unique($permissionNames));

            $role = $this->repository->syncPermissions($targetRole, $permissionNames);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });

        return ServiceResult::success([
            'role_id' => $role->id,
            'role' => $role->name,
            'permissions' => $this->repository->permissionNames($role),
        ], 'Role permissions updated successfully');
    }

    /**
     * @return array<int, string>
     */
    private function manageableRoleNames(User $user): array
    {
        if ($user->hasRole('clinic_admin')) {
            return UserRoleManager::clinicRoles();
        }

        if ($user->hasRole('lab_admin')) {
            return UserRoleManager::labRoles();
        }

        if ($user->hasRole('material_company_admin')) {
            return UserRoleManager::companyRoles();
        }

        return [];
    }

    /**
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    private function visibleModulesForRole(?string $roleName, array $permissions): array
    {
        $type = $this->frontendModuleType($roleName);

        if ($type === null) {
            return [];
        }

        $permissionLookup = array_flip($permissions);
        $modules = config("frontend_modules.{$type}", []);

        return collect($modules)
            ->filter(function (array $modulePermissions) use ($permissionLookup) {
                if ($modulePermissions === []) {
                    return true;
                }

                foreach ($modulePermissions as $permission) {
                    if (array_key_exists($permission, $permissionLookup)) {
                        return true;
                    }
                }

                return false;
            })
            ->keys()
            ->values()
            ->all();
    }

    private function frontendModuleType(?string $roleName): ?string
    {
        if ($roleName === 'patient') {
            return 'patient';
        }
        if ($roleName === 'super-admin') {
        return 'super-admin';
    }

        if (UserRoleManager::isClinicScopedRole($roleName)) {
            return 'clinic';
        }

        if (UserRoleManager::isLabScopedRole($roleName)) {
            return 'lab';
        }

        if (UserRoleManager::isCompanyScopedRole($roleName)) {
            return 'supplier';
        }

        return null;
    }
  public function modulesForType(User $user, string $type): array
{
    $ownType = $this->adminModuleType($user);

    if ($ownType === null) {
        return ServiceResult::error('Only admin roles can access this resource.', null, null, 403);
    }

    if ($ownType !== $type) {
        return ServiceResult::error('You are not allowed to access modules for this type.', null, null, 403);
    }

    $modules = config("frontend_modules.{$type}");

    if ($modules === null) {
        return ServiceResult::error('Invalid module type.', null, null, 422);
    }

    return ServiceResult::success(
        collect($modules)->keys()->values()->all(),
        'Modules fetched successfully'
    );
}

/**
 * يرجع نوع الموديولز المسموح للأدمن يشوفه، أو null لو مش أدمن أصلاً.
 */
private function adminModuleType(User $user): ?string
{
    if ($user->hasRole('super-admin')) {
        return 'super-admin';
    }

    if ($user->hasRole('clinic_admin')) {
        return 'clinic';
    }

    if ($user->hasRole('lab_admin')) {
        return 'lab';
    }

    if ($user->hasRole('material_company_admin')) {
        return 'supplier';
    }

    return null;
}
}
