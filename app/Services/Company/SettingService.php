<?php

namespace App\Services\Company;

use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;

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

    public function updateProfile(array $payload): array
    {
        $settings = CompanySetting::firstOrCreate(['company_id' => auth()->user()->company_id]);
        $company = auth()->user()->company;

        if (($payload['logo'] ?? null) instanceof UploadedFile) {
            $path = $payload['logo']->store('company/logos', 'public');
            $payload['logo_path'] = $path;
            $payload['logo_url'] = asset('storage/' . $path);
            unset($payload['logo']);
        }

        if ($company) {
            $company->update(array_filter([
                'name' => $payload['company_name'] ?? null,
                'address' => $payload['address'] ?? null,
                'website' => $payload['website'] ?? null,
                'description' => $payload['description'] ?? null,
                'logo_path' => $payload['logo_path'] ?? null,
                'logo_url' => $payload['logo_url'] ?? null,
            ], static fn ($value) => $value !== null));
        }

        $settings->update(['profile' => array_merge($settings->profile ?? [], $payload)]);

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
