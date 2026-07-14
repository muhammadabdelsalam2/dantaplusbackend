<?php

namespace App\Repositories\Access;

use App\Models\User;
use App\Repositories\Contracts\Access\RoleAccessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAccessRepository implements RoleAccessRepositoryInterface
{
    private string $guardName = 'web';

    public function findRoleByName(string $roleName): ?Role
    {
        return Role::query()
            ->where('name', $roleName)
            ->where('guard_name', $this->guardName)
            ->first();
    }

    public function findRoleOrFail(int|string $roleId): Role
    {
        $query = Role::query()->where('guard_name', $this->guardName);

        return is_numeric($roleId)
            ? $query->where('id', $roleId)->firstOrFail()
            : $query->where('name', $roleId)->firstOrFail();
    }

    public function rolesWithPermissions(): Collection
    {
        return Role::query()
            ->with(['permissions' => fn ($query) => $query
                ->where('guard_name', $this->guardName)
                ->orderBy('name')])
            ->where('guard_name', $this->guardName)
            ->orderBy('name')
            ->get();
    }

    public function permissionNames(Role $role): array
    {
        return $role->permissions()
            ->where('guard_name', $this->guardName)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    public function userPermissionNames(User $user): array
    {
        return $user->getAllPermissions()
            ->where('guard_name', $this->guardName)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $permissionNames = array_values(array_unique(array_map('trim', $permissionNames)));

        $permissions = Permission::query()
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $permissionNames)
            ->get();

        $role->syncPermissions($permissions);

        return $role->refresh();
    }
}
