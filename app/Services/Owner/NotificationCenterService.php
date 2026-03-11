<?php

namespace App\Services\Owner;

use App\Models\Notification;
use App\Repositories\NotificationRepository;
use App\Support\ServiceResult;

class NotificationCenterService
{
    public function __construct(private NotificationRepository $repository) {}

    public function list(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $notifications = $this->repository->paginate($filters, $perPage);

        $items = collect($notifications->items())
            ->map(fn (Notification $notification) => $this->mapNotification($notification))
            ->values()
            ->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ], 'Notifications fetched successfully');
    }

    public function create(array $data, bool $isTest = false): array
    {
        $sender = auth()->user();

        $notification = $this->repository->create([
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? 'Sent',
            'audience_type' => $data['audience_type'] ?? null,
            'audience_id' => $data['audience_id'] ?? null,
            'priority' => $data['priority'] ?? null,
            'delivery_methods' => $data['delivery_methods'] ?? [],
            'is_read' => false,
            'is_test' => $isTest,
            'sender_id' => $data['sender_id'] ?? $sender?->id,
            'sender_name' => $data['sender_name'] ?? $sender?->name,
            'link' => $data['link'] ?? null,
        ]);

        return ServiceResult::success(
            $this->mapNotification($notification),
            $isTest ? 'Test notification created successfully' : 'Notification created successfully',
            201
        );
    }

    public function markRead(int $id): array
    {
        $notification = $this->repository->findById($id);

        if (! $notification) {
            return ServiceResult::error('Notification not found', null, null, 404);
        }

        if (! $notification->is_read) {
            $notification = $this->repository->update($notification, [
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return ServiceResult::success($this->mapNotification($notification), 'Notification marked as read');
    }

    private function mapNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'status' => $notification->status,
            'audienceType' => $notification->audience_type,
            'audienceId' => $notification->audience_id,
            'priority' => $notification->priority,
            'deliveryMethods' => $notification->delivery_methods ?? [],
            'isRead' => (bool) $notification->is_read,
            'readAt' => optional($notification->read_at)->toISOString(),
            'isTest' => (bool) $notification->is_test,
            'senderId' => $notification->sender_id,
            'senderName' => $notification->sender_name,
            'link' => $notification->link,
            'createdAt' => optional($notification->created_at)->toISOString(),
            'updatedAt' => optional($notification->updated_at)->toISOString(),
        ];
    }
}
