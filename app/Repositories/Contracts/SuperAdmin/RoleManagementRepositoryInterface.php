<?php

namespace App\Repositories\Contracts\SuperAdmin;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleManagementRepositoryInterface
{
    public function listRoles(?string $q): Collection;

    public function findRoleOrFail(int $id): Role;

    public function createRole(array $data): Role;

    public function updateRole(Role $role, array $data): Role;

    public function deleteRole(Role $role): void;

    public function isRoleUsed(int $roleId): bool;

    public function usersCount(int $roleId): int;

    /**
     * @return array<int, string>
     */
    public function permissionNames(Role $role): array;

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): Role;
}
