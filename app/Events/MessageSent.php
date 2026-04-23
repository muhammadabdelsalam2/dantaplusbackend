<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new Channel('chat.' . $this->message->chat_id);
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }

    public function broadcastWith()
    {
        $user = $this->message->sender;

        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'sender_id' => $this->message->sender_id,

            'message' => $this->message->message,
            'type' => $this->message->type,

            // 👇 ADD USER INFO
            'sender' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,

                // 🔥 Spatie role (IMPORTANT)
                'roles' => $user->getRoleNames(), // collection
                'role' => $user->getRoleNames()->first(), // main role
            ],
        ];
    }
}