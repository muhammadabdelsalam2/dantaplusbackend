<?php

namespace App\Services\SuperAdmin;

use App\Models\DentalLab;
use App\Repositories\Lab\Settings\UserRepositoryInterface;
use App\Repositories\NotificationRepository;
use App\Support\ServiceResult;

class LabNotificationsService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function listForLab(DentalLab $lab): array
    {
        $users = $this->userRepository->listByLab($lab->id);
        $userMap = $users->keyBy('id');
        $userIds = $users->pluck('id')->all();

        $notifications = $this->notificationRepository->listForAudienceUsers($userIds);

        $items = $notifications->map(function ($notification) use ($userMap) {
            $recipient = $userMap->get($notification->audience_id);

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->message,
                'read' => (bool) $notification->is_read,
                'created_at' => optional($notification->created_at)->toISOString(),
                'recipient' => $recipient ? [
                    'id' => $recipient->id,
                    'full_name' => $recipient->name,
                    'role' => $recipient->role,
                ] : null,
            ];
        })->values()->all();

        $unread = $notifications->where('is_read', false)->count();

        return ServiceResult::success([
            'lab' => [
                'id' => $lab->id,
                'name' => $lab->name,
            ],
            'notifications' => $items,
            'meta' => [
                'total' => $notifications->count(),
                'unread' => $unread,
            ],
        ], 'Lab notifications fetched successfully');
    }
}
