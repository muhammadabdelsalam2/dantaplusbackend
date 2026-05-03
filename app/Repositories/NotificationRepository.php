<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationRepository
{
    public function query(): Builder
    {
        return Notification::query()->with(['user:id,name,role', 'sender:id,name']);
    }

    public function queryForUser(User $user, string $role): Builder
    {
        return $this->query()->where(function (Builder $query) use ($user, $role) {
            $query->where('user_id', $user->id)
                ->orWhere(function (Builder $roleQuery) use ($role) {
                    $roleQuery->whereNull('user_id')
                        ->where('role', $role);
                })
                ->orWhere(function (Builder $legacyQuery) use ($user, $role) {
                    $legacyQuery->where('audience_type', 'user')
                        ->where('audience_id', $user->id)
                        ->orWhere('audience_type', $role);
                });
        });
    }

    public function paginateQuery(Builder $query, int $perPage = 15): LengthAwarePaginator
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function listForAudienceUsers(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return Notification::query()
            ->where(function (Builder $query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhere(function (Builder $legacyQuery) use ($userIds) {
                        $legacyQuery->where('audience_type', 'user')
                            ->whereIn('audience_id', $userIds);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id): ?Notification
    {
        return Notification::query()->find($id);
    }

    public function findForUser(User $user, string $role, int $id): ?Notification
    {
        return $this->queryForUser($user, $role)
            ->whereKey($id)
            ->first();
    }

    public function create(array $data): Notification
    {
        return Notification::query()->create($data);
    }

    public function update(Notification $notification, array $data): Notification
    {
        $notification->update($data);

        return $notification->refresh();
    }

    public function markAsRead(Builder $query): int
    {
        return $query->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
