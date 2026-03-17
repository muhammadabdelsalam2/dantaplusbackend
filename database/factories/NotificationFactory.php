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
            'type' => $this->faker->randomElement(['NEW_CASE', 'NEW_MESSAGE', 'CASE_DELIVERED', 'SYSTEM_ALERT']),
            'status' => 'Sent',
            'audience_type' => 'lab',
            'audience_id' => $this->faker->numberBetween(1, 5),
            'priority' => $this->faker->randomElement(['Low', 'Normal', 'High']),
            'delivery_methods' => ['system'],
            'is_read' => false,
            'read_at' => null,
            'is_test' => false,
            'sender_id' => $senderId,
            'sender_name' => $senderId ? User::find($senderId)?->name : 'System',
            'link' => null,
        ];
    }
}
