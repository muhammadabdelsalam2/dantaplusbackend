<?php

namespace App\Repositories\Contracts\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleAccessRepositoryInterface
{
    public function findRoleByName(string $roleName): ?Role;

    public function findRoleOrFail(int $roleId): Role;

    public function rolesWithPermissions(): Collection;

    /**
     * @return array<int, string>
     */
    public function permissionNames(Role $role): array;

    /**
     * @return array<int, string>
     */
    public function userPermissionNames(User $user): array;

    /**
     * @param array<int, string> $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): Role;
}
