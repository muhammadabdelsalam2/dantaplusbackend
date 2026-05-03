<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\NotificationLogRepository;
use App\Repositories\NotificationRepository;
use App\Support\ServiceResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationService
{
    private const CLINIC_ROLES = [
        'clinic_admin',
        'doctor',
        'nurse',
        'accountant',
        'receptionist',
        'staff',
    ];

    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationLogRepository $notificationLogRepository
    ) {
    }

    public function listNotifications(array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $query = $this->filterNotifications($this->notificationRepository->query(), $filters);
        $notifications = $this->notificationRepository->paginateQuery($query, $perPage);

        return ServiceResult::success(
            $this->buildCollectionPayload($notifications),
            'Notifications fetched successfully'
        );
    }

    public function createNotification(array $data, ?User $sender = null, bool $isTest = false): Notification
    {
        $deliveryMethods = $this->normalizeDeliveryMethods($data);
        $recipient = isset($data['user_id']) ? User::query()->find($data['user_id']) : null;
        $role = $this->resolveNotificationRole($recipient, $data['role'] ?? null);

        return $this->notificationRepository->create([
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'] ?? 'custom',
            'status' => $data['delivery_status'] ?? $data['status'] ?? 'sent',
            'audience_type' => $data['audience_type'] ?? ($recipient ? 'user' : $role),
            'audience_id' => $data['audience_id'] ?? $recipient?->id,
            'priority' => $data['priority'] ?? 'medium',
            'delivery_method' => $deliveryMethods,
            'delivery_methods' => $deliveryMethods,
            'user_id' => $recipient?->id,
            'role' => $role,
            'is_read' => false,
            'read_at' => null,
            'is_test' => $isTest,
            'sender_id' => $data['sender_id'] ?? $sender?->id,
            'sender_name' => $data['sender_name'] ?? $sender?->name,
            'link' => $data['link'] ?? null,
        ]);
    }

    public function sendNotification(array $data, ?User $sender = null, bool $isTest = false): array
    {
        $sender ??= auth()->user();
        $recipients = $this->resolveRecipients($data, $sender);

        if ($recipients->isEmpty()) {
            return ServiceResult::error('No recipients matched the notification target.', null, [
                'user_id' => ['A valid recipient is required.'],
            ], 422);
        }

        $notifications = $recipients->map(function (User $recipient) use ($data, $sender, $isTest) {
            $notification = $this->createNotification([
                ...$data,
                'user_id' => $recipient->id,
                'role' => $this->resolveNotificationRole($recipient, $data['role'] ?? null),
            ], $sender, $isTest);

            $this->logNotificationDelivery($notification, $recipient);
            $this->broadcastNotification($notification);

            return $notification->refresh();
        });

        $payload = [
            'items' => $notifications->map(fn (Notification $notification) => $this->mapNotification($notification))->values()->all(),
            'totalSent' => $notifications->count(),
        ];

        return ServiceResult::success(
            $payload,
            $isTest ? 'Test notification sent successfully' : 'Notification sent successfully',
            201
        );
    }

    public function markAsRead(int $id, ?User $actor = null, bool $restrictToActor = true): array
    {
        $actor ??= auth()->user();

        $notification = $restrictToActor && $actor
            ? $this->notificationRepository->findForUser($actor, $this->resolveActorRole($actor), $id)
            : $this->notificationRepository->findById($id);

        if (! $notification) {
            return ServiceResult::error('Notification not found', null, null, 404);
        }

        if (! $notification->is_read) {
            $notification = $this->notificationRepository->update($notification, [
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return ServiceResult::success($this->mapNotification($notification), 'Notification marked as read');
    }

    public function markAllAsRead(User $actor, array $filters = []): array
    {
        $query = $this->filterNotifications(
            $this->notificationRepository->queryForUser($actor, $this->resolveActorRole($actor)),
            $filters
        );

        $updated = $this->notificationRepository->markAsRead($query);

        return ServiceResult::success([
            'updatedCount' => $updated,
        ], 'Notifications marked as read successfully');
    }

    public function getUserNotifications(User $actor, array $filters = [], bool $onlyUnread = false): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $query = $this->filterNotifications(
            $this->notificationRepository->queryForUser($actor, $this->resolveActorRole($actor)),
            $filters
        );

        if ($onlyUnread) {
            $query->where('is_read', false);
        }

        $notifications = $this->notificationRepository->paginateQuery($query, $perPage);

        return ServiceResult::success(
            $this->buildCollectionPayload($notifications, $actor),
            $onlyUnread ? 'Unread notifications fetched successfully' : 'Notifications fetched successfully'
        );
    }

    public function filterNotifications(Builder $query, array $filters): Builder
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $builder, string $type) => $builder->where('type', $type))
            ->when($filters['priority'] ?? null, fn (Builder $builder, string $priority) => $builder->where('priority', $priority))
            ->when($filters['role'] ?? null, fn (Builder $builder, string $role) => $builder->where('role', $role))
            ->when($filters['user_id'] ?? null, fn (Builder $builder, int $userId) => $builder->where('user_id', $userId))
            ->when($filters['date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('created_at', $date))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $from) => $builder->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $to) => $builder->whereDate('created_at', '<=', $to));

        if (($filters['status'] ?? null) === 'read') {
            $query->where('is_read', true);
        }

        if (($filters['status'] ?? null) === 'unread') {
            $query->where('is_read', false);
        }

        return $query;
    }

    private function buildCollectionPayload($notifications, ?User $actor = null): array
    {
        return [
            'items' => collect($notifications->items())
                ->map(fn (Notification $notification) => $this->mapNotification($notification))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'summary' => [
                'unread' => $actor
                    ? $this->notificationRepository
                        ->queryForUser($actor, $this->resolveActorRole($actor))
                        ->where('is_read', false)
                        ->count()
                    : $this->notificationRepository->query()->where('is_read', false)->count(),
            ],
        ];
    }

    private function mapNotification(Notification $notification): array
    {
        $deliveryMethods = $this->normalizeStoredDeliveryMethods($notification);

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'priority' => $notification->priority,
            'status' => $notification->is_read ? 'read' : 'unread',
            'deliveryStatus' => $notification->status,
            'deliveryMethod' => $deliveryMethods,
            'deliveryMethods' => $deliveryMethods,
            'userId' => $notification->user_id,
            'role' => $notification->role,
            'isRead' => (bool) $notification->is_read,
            'timestamp' => optional($notification->created_at)->toISOString(),
            'createdAt' => optional($notification->created_at)->toISOString(),
            'readAt' => optional($notification->read_at)->toISOString(),
            'senderId' => $notification->sender_id,
            'senderName' => $notification->sender_name,
            'link' => $notification->link,
            'isTest' => (bool) $notification->is_test,
        ];
    }

    private function resolveRecipients(array $data, ?User $sender = null): Collection
    {
        if (! empty($data['user_id'])) {
            $user = User::query()->find($data['user_id']);

            return $user ? collect([$user]) : collect();
        }

        $requestedRole = $data['role'] ?? null;

        if ($requestedRole === 'clinic') {
            return User::query()->role(self::CLINIC_ROLES)->get();
        }

        if ($requestedRole === 'super_admin') {
            return User::query()->role('super-admin')->get();
        }

        if ($requestedRole === 'owner') {
            return User::query()
                ->whereNull('clinic_id')
                ->whereNull('lab_id')
                ->whereNull('company_id')
                ->get();
        }

        return $sender ? collect([$sender]) : collect();
    }

    private function resolveActorRole(User $user): string
    {
        if ($user->hasRole('super-admin') || $user->role === 'super-admin') {
            return 'super_admin';
        }

        if (in_array($user->role, self::CLINIC_ROLES, true)) {
            return 'clinic';
        }

        return 'owner';
    }

    private function resolveNotificationRole(?User $recipient, ?string $requestedRole): ?string
    {
        if ($requestedRole) {
            return $requestedRole;
        }

        return $recipient ? $this->resolveActorRole($recipient) : null;
    }

    private function normalizeDeliveryMethods(array $data): array
    {
        $methods = $data['delivery_method']
            ?? $data['delivery_methods']
            ?? $data['deliveryMethod']
            ?? ['in_app'];

        return array_values(array_filter((array) $methods));
    }

    private function normalizeStoredDeliveryMethods(Notification $notification): array
    {
        return array_values(array_filter(
            $notification->delivery_method
            ?? $notification->delivery_methods
            ?? []
        ));
    }

    private function logNotificationDelivery(Notification $notification, User $recipient): void
    {
        if (! $recipient->clinic_id) {
            return;
        }

        foreach ($this->normalizeStoredDeliveryMethods($notification) as $channel) {
            $this->notificationLogRepository->create([
                'clinic_id' => $recipient->clinic_id,
                'doctor_id' => $recipient->doctor?->id,
                'channel' => $channel,
                'status' => 'sent',
                'message_content' => $notification->message,
                'sent_at' => now(),
            ]);
        }
    }

    private function broadcastNotification(Notification $notification): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        broadcast(new NotificationCreated($notification))->toOthers();
    }
}
