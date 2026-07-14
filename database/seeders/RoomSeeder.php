<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    private const ROOMS_PER_CLINIC = 3;

    /**
     * لو عايز عيادات معينة بس، حط الـ IDs هنا.
     * سيبها فاضية [] لو عايز كل العيادات.
     */
    private const TARGET_CLINIC_IDS = [26];

    public function run(): void
    {
        $clinicIds = self::TARGET_CLINIC_IDS !== []
            ? self::TARGET_CLINIC_IDS
            : \App\Models\Clinic::query()->pluck('id')->all();

        foreach ($clinicIds as $clinicId) {
            for ($i = 1; $i <= self::ROOMS_PER_CLINIC; $i++) {
                Room::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'name' => "Room {$i}",
                    ],
                    [
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Rooms seeded for clinic(s): ' . implode(', ', $clinicIds));
    }
}
