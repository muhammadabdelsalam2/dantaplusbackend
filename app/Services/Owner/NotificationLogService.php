<?php

namespace App\Services\Owner;

use App\Models\NotificationLog;
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
                'clinicId' => $log->clinic_id,
                'clinicName' => $log->clinic?->name,
                'doctorId' => $log->doctor_id,
                'channel' => $log->channel,
                'status' => $log->status,
                'messageContent' => $log->message_content,
                'sentAt' => optional($log->sent_at)->toISOString(),
                'createdAt' => optional($log->created_at)->toISOString(),
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
        ], 'Notification logs fetched successfully');
    }
}
