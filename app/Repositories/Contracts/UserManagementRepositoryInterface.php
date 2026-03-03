<?php

namespace App\Repositories\Contracts\SuperAdmin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserManagementRepositoryInterface
{
    public function paginateUsers(?string $q, ?string $role, ?string $status, int $perPage): LengthAwarePaginator;

    public function findUserOrFail(int $id): User;

    public function createUser(array $data): User;

    public function updateUser(User $user, array $data): User;

    public function deleteUser(User $user): void;
}
