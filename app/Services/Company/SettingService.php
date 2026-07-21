<?php

namespace App\Services\Company;

use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    public function getSettings(): array
    {
        $company = auth()->user()->company;
        $settings = CompanySetting::firstOrCreate(
            ['company_id' => auth()->user()->company_id],
            ['communication' => [], 'automation' => $this->defaultAutomation()]
        );

        return [
            'profile' => [
                'company_name'  => $company->name,
                'contact_email' => $company->email,
                'phone'         => $company->phone,
                'website'       => $company->website,
                'address'       => $company->address,
                'description'   => $company->description,
                'logo_url'      => $company->logo_url,
            ],
            'communication' => $settings->communication ?? [],
            'automation'    => $this->sanitizeAutomation($settings->automation ?? []),
        ];
    }

    public function updateProfile(array $payload): array
    {
        $company = auth()->user()->company;

        if (($payload['logo'] ?? null) instanceof UploadedFile) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $path = $payload['logo']->store('company/logos', 'public');
            $payload['logo_path'] = $path;
            $payload['logo_url'] = asset('storage/' . $path);
            unset($payload['logo']);
        }

        $company->update(array_filter([
            'name'        => $payload['company_name'] ?? null,
            'email'       => $payload['contact_email'] ?? null,
            'phone'       => $payload['phone'] ?? null,
            'address'     => $payload['address'] ?? null,
            'website'     => $payload['website'] ?? null,
            'description' => $payload['description'] ?? null,
            'logo_path'   => $payload['logo_path'] ?? null,
            'logo_url'    => $payload['logo_url'] ?? null,
        ], static fn ($value) => $value !== null));

        return $this->getSettings();
    }

    public function updateSection(string $section, array $payload): array
    {
        $settings = CompanySetting::firstOrCreate(['company_id' => auth()->user()->company_id]);

        if ($section === 'automation') {
            $payload = array_merge($this->sanitizeAutomation($settings->automation ?? []), $this->sanitizeAutomation($payload));
        }

        $settings->update([$section => $payload]);
        return $this->getSettings();
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

    private function defaultAutomation(): array
    {
        return [
            'auto_transfer_to_payments'    => false,
            'auto_create_invoice_billing'  => false,
            'whatsapp_notification_clinic' => false,
            'auto_pdf_generation'          => false,
        ];
    }

    private function sanitizeAutomation(array $payload): array
    {
        $allowed = array_keys($this->defaultAutomation());
        $filtered = array_intersect_key($payload, array_flip($allowed));

        return array_merge(
            $this->defaultAutomation(),
            array_map(static fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN), $filtered)
        );
    }
}
