<?php

namespace App\Repositories\Lab\Settings;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function paginateByLab(int $labId, int $perPage = 20): LengthAwarePaginator;

    public function listByLab(int $labId): Collection;

    public function findByLabAndId(int $labId, int $userId): ?User;

    public function usernameExists(int $labId, string $username, ?int $excludeUserId = null): bool;

    public function countActiveLabAdmins(int $labId, ?int $excludeUserId = null): int;

    public function create(array $data): User;

    public function update(User $user, array $data): User;
}
