<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['system', 'reminder', 'announcement'];
        $priorities = ['Low', 'Medium', 'High'];
        $audiences = ['clinic', 'lab', 'patient', 'company', 'all'];
        $methods = ['email', 'sms', 'whatsapp', 'push'];
        $sender = User::query()->role('super-admin')->first() ?? User::query()->first();

        for ($i = 1; $i <= 20; $i++) {
            $isRead = (bool) random_int(0, 1);
            $deliveryMethods = Arr::random($methods, random_int(1, 2));

            Notification::create([
                'title' => 'Notification #' . $i,
                'message' => 'This is a demo notification message ' . Str::random(12),
                'type' => Arr::random($types),
                'status' => 'Sent',
                'audience_type' => Arr::random($audiences),
                'audience_id' => null,
                'priority' => Arr::random($priorities),
                'delivery_methods' => array_values((array) $deliveryMethods),
                'is_read' => $isRead,
                'read_at' => $isRead ? now()->subDays(random_int(0, 10)) : null,
                'is_test' => false,
                'sender_id' => $sender?->id,
                'sender_name' => $sender?->name ?? 'System',
                'link' => '/dashboard/notifications/' . $i,
            ]);
        }
    }
}
