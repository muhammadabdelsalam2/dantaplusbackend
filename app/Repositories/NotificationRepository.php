<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class NotificationRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Notification::query()
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters['audience_type'] ?? null, fn (Builder $q, $audienceType) => $q->where('audience_type', $audienceType));

        if (array_key_exists('is_read', $filters)) {
            $query->where('is_read', (bool) $filters['is_read']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Notification
    {
        return Notification::query()->find($id);
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
}
