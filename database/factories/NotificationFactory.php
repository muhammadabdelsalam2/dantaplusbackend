<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $senderId = User::query()->inRandomOrder()->value('id');

        return [
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->sentence(10),
            'type' => $this->faker->randomElement(['system', 'payment', 'appointment', 'custom']),
            'status' => 'sent',
            'audience_type' => 'user',
            'audience_id' => $senderId,
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'delivery_method' => ['in_app'],
            'delivery_methods' => ['in_app'],
            'user_id' => $senderId,
            'role' => 'super_admin',
            'is_read' => false,
            'read_at' => null,
            'is_test' => false,
            'sender_id' => $senderId,
            'sender_name' => $senderId ? User::find($senderId)?->name : 'System',
            'link' => null,
        ];
    }
}
