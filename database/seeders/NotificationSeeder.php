<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $sender = User::query()->role('super-admin')->first() ?? User::query()->first();
        $clinicUsers = User::query()->role(['clinic_admin', 'doctor', 'nurse', 'accountant', 'receptionist', 'staff'])->take(4)->get();
        $superAdmins = User::query()->role('super-admin')->take(2)->get();

        $templates = collect([
            [
                'title' => 'System maintenance scheduled',
                'message' => 'The system will receive a short maintenance update tonight.',
                'type' => 'system',
                'priority' => 'high',
                'delivery_method' => ['in_app', 'email'],
            ],
            [
                'title' => 'Payment received successfully',
                'message' => 'A clinic payment was recorded and matched to today\'s invoices.',
                'type' => 'payment',
                'priority' => 'medium',
                'delivery_method' => ['in_app', 'whatsapp'],
            ],
            [
                'title' => 'Appointment reminder',
                'message' => 'You have a patient appointment coming up within the next hour.',
                'type' => 'appointment',
                'priority' => 'high',
                'delivery_method' => ['in_app', 'sms'],
            ],
            [
                'title' => 'Real-time test notification',
                'message' => 'This notification is used to verify polling or websocket updates.',
                'type' => 'custom',
                'priority' => 'low',
                'delivery_method' => ['in_app'],
            ],
        ]);

        $recipients = $clinicUsers->concat($superAdmins)->filter();

        foreach ($recipients as $index => $recipient) {
            foreach ($templates as $templateIndex => $template) {
                $isRead = ($index + $templateIndex) % 2 === 0;

                Notification::create([
                    'title' => $template['title'],
                    'message' => $template['message'] . ' ' . Str::random(8),
                    'type' => $template['type'],
                    'status' => 'sent',
                    'audience_type' => 'user',
                    'audience_id' => $recipient->id,
                    'priority' => $template['priority'],
                    'delivery_method' => $template['delivery_method'],
                    'delivery_methods' => $template['delivery_method'],
                    'user_id' => $recipient->id,
                    'role' => $recipient->hasRole('super-admin') ? 'super_admin' : 'clinic',
                    'is_read' => $isRead,
                    'read_at' => $isRead ? now()->subDays(random_int(0, 5)) : null,
                    'is_test' => $template['title'] === 'Real-time test notification',
                    'sender_id' => $sender?->id,
                    'sender_name' => $sender?->name ?? 'System',
                    'link' => '/dashboard/notifications/' . ($index + 1),
                ]);
            }
        }
    }
}
