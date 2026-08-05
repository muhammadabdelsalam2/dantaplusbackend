<?php

namespace App\Selects\Clinic;

use App\Models\User;

class MessagingSelects
{
    public function toArray(?int $clinicId): array
    {
        if (! $clinicId) {
            return [
                'channels' => $this->channels(),
                'message_types' => $this->messageTypes(),
                'doctors' => [],
            ];
        }

        return [
            'channels' => $this->channels(),
            'message_types' => $this->messageTypes(),
            'doctors' => User::query()
                ->where('clinic_id', $clinicId)
                ->role('doctor')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $doctor) => ['id' => $doctor->id, 'name' => $doctor->name])
                ->values()
                ->all(),
        ];
    }

    private function channels(): array
    {
        return [
            ['id' => 'sms', 'name' => 'SMS'],
            ['id' => 'whatsapp', 'name' => 'WhatsApp'],
        ];
    }

    private function messageTypes(): array
    {
        return [
            ['id' => 'confirmation', 'name' => 'Confirmation'],
            ['id' => 'reminder', 'name' => 'Reminder'],
            ['id' => 'follow_up', 'name' => 'Follow Up'],
            ['id' => 'custom', 'name' => 'Custom'],
        ];
    }
}
