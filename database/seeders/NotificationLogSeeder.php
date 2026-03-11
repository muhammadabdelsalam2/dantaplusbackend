<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\NotificationLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class NotificationLogSeeder extends Seeder
{
    public function run(): void
    {
        $clinicIds = Clinic::query()->pluck('id')->all();
        $doctorIds = Doctor::query()->pluck('id')->all();
        $channels = ['sms', 'whatsapp'];
        $statuses = ['sent', 'failed', 'queued'];

        if (empty($clinicIds)) {
            return;
        }

        for ($i = 1; $i <= 20; $i++) {
            NotificationLog::create([
                'clinic_id' => Arr::random($clinicIds),
                'doctor_id' => ! empty($doctorIds) && random_int(0, 1) === 1 ? Arr::random($doctorIds) : null,
                'channel' => Arr::random($channels),
                'status' => Arr::random($statuses),
                'message_content' => 'Reminder message #' . $i,
                'sent_at' => now()->subDays(random_int(0, 15)),
            ]);
        }
    }
}
