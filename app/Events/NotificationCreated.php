<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.user.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
            'priority' => $this->notification->priority,
            'deliveryMethod' => $this->notification->delivery_method ?? $this->notification->delivery_methods ?? [],
            'isRead' => (bool) $this->notification->is_read,
            'timestamp' => optional($this->notification->created_at)->toISOString(),
        ];
    }
}
