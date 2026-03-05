<?php

namespace App\Repositories\SuperAdmin;

use App\Repositories\Contracts\SuperAdmin\RoleManagementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementRepository implements RoleManagementRepositoryInterface
{
    private string $guardName = 'web';

    public function listRoles(?string $q): Collection
    {
        $q = $q !== null ? trim($q) : null;

        return Role::query()
            ->select(['id', 'name', 'guard_name'])
            ->where('guard_name', $this->guardName)
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get();
    }

    public function findRoleOrFail(int $id): Role
    {
        return Role::query()
            ->where('guard_name', $this->guardName)
            ->findOrFail($id);
    }

    public function createRole(array $data): Role
    {
        return Role::query()->create([
            'name' => $data['name'],
            'guard_name' => $this->guardName,
        ]);
    }

    public function updateRole(Role $role, array $data): Role
    {
        // لا نسمح بتغيير guard_name عبر API
        unset($data['guard_name']);

        $role->update($data);

        return $role->refresh();
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    public function isRoleUsed(int $roleId): bool
    {
        return DB::table('model_has_roles')->where('role_id', $roleId)->exists();
    }

    public function usersCount(int $roleId): int
    {
        return (int) DB::table('model_has_roles')->where('role_id', $roleId)->count();
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
