<?php

namespace App\Services\Owner;

use App\Models\NotificationLog;
use App\Models\Clinic;
use App\Models\User;
use App\Repositories\NotificationLogRepository;
use App\Support\ServiceResult;

class NotificationLogService
{
    public function __construct(private NotificationLogRepository $repository) {}

    public function list(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $logs = $this->repository->paginate($filters, $perPage);

        $items = collect($logs->items())
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'message' => $log->message_content,
                'status' => $log->status,
                'channel' => $log->channel,
                'clinic' => $log->clinic?->name,
                'doctor' => $log->doctor?->user?->name,
                'date_sent' => optional($log->sent_at)->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'channels' => collect(['All Channels'])
                    ->concat(NotificationLog::query()->whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel'))
                    ->values()
                    ->all(),
                'doctors' => User::query()
                    ->whereHas('roles', fn ($query) => $query->where('name', 'doctor'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                    ->all(),
                'clinics' => Clinic::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Clinic $clinic) => ['id' => $clinic->id, 'name' => $clinic->name])
                    ->values()
                    ->all(),
            ],
        ], 'Notification logs fetched successfully');
    }
}
