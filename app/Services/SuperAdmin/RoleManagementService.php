<?php

namespace App\Services\SuperAdmin;

use App\Repositories\Contracts\SuperAdmin\RoleManagementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class RoleManagementService
{
    private string $guardName = 'web';

    public function __construct(
        private RoleManagementRepositoryInterface $repo
    ) {}

    public function list(?string $q): Collection
    {
        return $this->repo->listRoles($q);
    }

    public function show(Role $role): Role
    {
        $this->assertSameGuard($role);

        // attach computed fields (resource will read them)
        $role->permissions_list = $this->repo->permissionNames($role);
        $role->users_count = $this->repo->usersCount($role->id);

        return $role;
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = $this->repo->createRole($data);

            if (array_key_exists('permissions', $data)) {
                $this->repo->syncPermissions($role, $data['permissions'] ?? []);
            }

            // ✅ Fix architectural issue: spatie caches roles/permissions aggressively
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        $this->assertSameGuard($role);
        $this->assertNotSuperAdmin($role, 'updated');

        return DB::transaction(function () use ($role, $data) {
            $updated = $this->repo->updateRole($role, $data);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $updated;
        });
    }

    public function delete(Role $role): void
    {
        $this->assertSameGuard($role);

        if ($this->isSuperAdmin($role)) {
            throw new \DomainException('super-admin role cannot be deleted.');
        }

        if ($this->repo->isRoleUsed($role->id)) {
            throw new \DomainException('Role is in use and cannot be deleted.');
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]); // clean pivot
            $this->repo->deleteRole($role);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $this->assertSameGuard($role);
        $this->assertNotSuperAdmin($role, 'updated');

        return DB::transaction(function () use ($role, $permissions) {
            $role = $this->repo->syncPermissions($role, $permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // attach computed fields for response
            $role->permissions_list = $this->repo->permissionNames($role);
            $role->users_count = $this->repo->usersCount($role->id);

            return $role;
        });
    }

    private function assertSameGuard(Role $role): void
    {
        if ($role->guard_name !== $this->guardName) {
            throw new \DomainException('Invalid role guard.');
        }
    }

    private function isSuperAdmin(Role $role): bool
    {
        // Protect by name + extra safety by id=1 in your DB
        return $role->name === 'super-admin' || $role->id === 1;
    }

    private function assertNotSuperAdmin(Role $role, string $verb): void
    {
        if ($this->isSuperAdmin($role)) {
            throw new \DomainException("super-admin role cannot be {$verb}.");
        }
    }
}
