<?php

namespace App\Repositories\SuperAdmin;

use App\Models\User;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserManagementRepository implements UserManagementRepositoryInterface
{
    public function paginateUsers(?string $q, ?string $role, ?string $status, int $perPage): LengthAwarePaginator
    {
        $q = $q !== null ? trim($q) : null;

        return User::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', 1))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', 0))
            ->when($role, function ($query) use ($role) {
                $query->whereHas('roles', fn ($r) => $r->where('name', $role));
            })
            ->with(['roles:id,name','doctor:id,user_id','patient:id,user_id'])
            ->latest('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function findUserOrFail(int $id): User
    {
        return User::with(['roles:id,name','doctor:id,user_id','patient:id,user_id'])->findOrFail($id);
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);
        return $user->refresh();
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }
}
