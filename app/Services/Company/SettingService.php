<?php

namespace App\Services\Company;

use App\Models\CompanySetting;
use App\Models\ShippingZone;

class SettingService
{
    public function getSettings(): array
    {
        $settings = CompanySetting::firstOrCreate(
            ['company_id' => auth()->user()->company_id],
            ['profile' => [], 'communication' => [], 'automation' => []]
        );

        return $settings->toArray();
    }

    public function updateSection(string $section, array $payload): array
    {
        $settings = CompanySetting::firstOrCreate(['company_id' => auth()->user()->company_id]);
        $settings->update([$section => $payload]);
        return $settings->fresh()->toArray();
    }

    public function testCommunication(): array
    {
        return ['status' => 'queued', 'tested_at' => now()->toISOString()];
    }

    public function whatsappLogs(): array
    {
        $settings = CompanySetting::firstOrCreate(['company_id' => auth()->user()->company_id]);
        $communication = $settings->communication ?? [];
        return $communication['logs'] ?? [];
    }
}
