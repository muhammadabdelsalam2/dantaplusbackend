<?php

namespace App\Repositories\Lab\Settings;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class UserRepository implements UserRepositoryInterface
{
    public function paginateByLab(int $labId, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->where('lab_id', $labId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function listByLab(int $labId): Collection
    {
        return User::query()
            ->where('lab_id', $labId)
            ->orderByDesc('id')
            ->get();
    }

    public function findByLabAndId(int $labId, int $userId): ?User
    {
        return User::query()
            ->where('lab_id', $labId)
            ->where('id', $userId)
            ->first();
    }

    public function usernameExists(int $labId, string $username, ?int $excludeUserId = null): bool
    {
        $query = User::query()
            ->where('lab_id', $labId)
            ->where('username', $username);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    public function countActiveLabAdmins(int $labId, ?int $excludeUserId = null): int
    {
        $query = User::query()
            ->where('lab_id', $labId)
            ->where('role', LabRole::LabAdmin->value)
            ->where(function (Builder $q) {
                $q->where('status', UserStatus::Active->value)
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('status')->where('is_active', true);
                    });
            });

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return (int) $query->count();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }
}
