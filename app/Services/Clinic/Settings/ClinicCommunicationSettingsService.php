<?php

namespace App\Services\Clinic\Settings;

use App\Models\Clinic\CommunicationSettings;
use App\Models\Clinic\MessageLog;
use App\Models\Clinic\MessageTemplate;
use App\Support\ServiceResult;

class ClinicCommunicationSettingsService
{
    public function show(): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            $this->buildPayload($this->settings($clinicId), $clinicId),
            'Communication settings fetched successfully.'
        );
    }

    public function updateWhatsApp(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $settings = $this->settings($clinicId);
        $settings->fill($this->sanitizeSensitivePayload($data, [
            'whatsapp_access_token',
            'whatsapp_app_secret',
            'whatsapp_webhook_verify_token',
        ]));
        $settings->save();

        return ServiceResult::success(
            ['saved' => true],
            'WhatsApp communication settings updated successfully.'
        );
    }

    public function updateSmsEmail(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $settings = $this->settings($clinicId);
        $settings->fill($this->sanitizeSensitivePayload($data, [
            'sms_api_key',
            'smtp_password',
        ]));
        $settings->save();

        return ServiceResult::success(
            ['saved' => true],
            'SMS and email communication settings updated successfully.'
        );
    }

    public function testConnection(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        if ($clinicId !== (int) $data['clinic_id']) {
            return ServiceResult::error('You are not authorized to access this clinic.', null, [
                'clinic_id' => ['The selected clinic is invalid for the current user.'],
            ], 403);
        }

        $settings = $this->settings($clinicId);
        $channel = $data['channel'];

        $status = $channel === 'whatsapp'
            ? $this->hasWhatsAppConfiguration($settings)
            : ($channel === 'sms'
                ? $this->hasSmsConfiguration($settings)
                : $this->hasEmailConfiguration($settings));

        $latestLog = MessageLog::query()
            ->where('clinic_id', $clinicId)
            ->where('channel', $channel)
            ->latest('sent_at')
            ->first();

        $totalSent24h = MessageLog::query()
            ->where('clinic_id', $clinicId)
            ->where('channel', $channel)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subDay())
            ->count();

        return ServiceResult::success([
            'success' => $status,
            'status' => $status ? 'connected' : 'not_configured',
            'provider' => $channel === 'whatsapp'
                ? ($settings->whatsapp_provider ?: 'meta_cloud_api')
                : ($channel === 'sms'
                    ? ($settings->sms_sender_name ?: 'sms')
                    : ($settings->smtp_from_email ?: 'email')),
            'last_message_at' => optional($latestLog?->sent_at)->toDateTimeString(),
            'total_sent_24h' => $totalSent24h,
        ], 'Communication connection test completed successfully.');
    }

    public function webhookUrl(): array
    {
        return ServiceResult::success([
            'webhook_url' => rtrim(config('app.url'), '/') . '/api/clinic/settings/communication/webhook-url',
        ], 'Webhook URL fetched successfully.');
    }

    public function updateTemplate(int $id, array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $template = MessageTemplate::query()
            ->where('clinic_id', $clinicId)
            ->find($id);

        if (! $template) {
            return ServiceResult::error('Template not found for this clinic.', null, [
                'id' => ['Template not found for this clinic.'],
            ], 404);
        }

        $template->update([
            'body' => $data['body'],
        ]);

        return ServiceResult::success([
            'id' => $template->id,
        ], 'Template updated successfully.');
    }

    private function buildPayload(CommunicationSettings $settings, int $clinicId): array
    {
        return [
            'clinic_id' => $clinicId,
            'whatsapp' => [
                'whatsapp_provider' => $settings->whatsapp_provider,
                'whatsapp_phone_number_id' => $settings->whatsapp_phone_number_id,
                'whatsapp_business_account_id' => $settings->whatsapp_business_account_id,
                'whatsapp_access_token' => $this->mask($settings->whatsapp_access_token),
                'whatsapp_app_id' => $settings->whatsapp_app_id,
                'whatsapp_app_secret' => $this->mask($settings->whatsapp_app_secret),
                'whatsapp_webhook_verify_token' => $this->mask($settings->whatsapp_webhook_verify_token),
            ],
            'sms_email' => [
                'sms_api_key' => $this->mask($settings->sms_api_key),
                'sms_sender_name' => $settings->sms_sender_name,
                'smtp_host' => $settings->smtp_host,
                'smtp_port' => $settings->smtp_port,
                'smtp_username' => $settings->smtp_username,
                'smtp_password' => $this->mask($settings->smtp_password),
                'smtp_encryption' => $settings->smtp_encryption,
                'smtp_from_name' => $settings->smtp_from_name,
                'smtp_from_email' => $settings->smtp_from_email,
            ],
            'templates' => MessageTemplate::query()
                ->where('clinic_id', $clinicId)
                ->orderByDesc('id')
                ->get(['id', 'name', 'message_type', 'channel', 'body', 'is_active'])
                ->toArray(),
        ];
    }

    private function settings(int $clinicId): CommunicationSettings
    {
        return CommunicationSettings::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            ['whatsapp_provider' => 'meta_cloud_api']
        );
    }

    private function hasWhatsAppConfiguration(CommunicationSettings $settings): bool
    {
        return ! empty($settings->whatsapp_provider)
            && ! empty($settings->whatsapp_phone_number_id)
            && ! empty($settings->whatsapp_business_account_id)
            && ! empty($settings->whatsapp_access_token);
    }

    private function hasSmsConfiguration(CommunicationSettings $settings): bool
    {
        return ! empty($settings->sms_api_key) && ! empty($settings->sms_sender_name);
    }

    private function hasEmailConfiguration(CommunicationSettings $settings): bool
    {
        return ! empty($settings->smtp_host)
            && ! empty($settings->smtp_port)
            && ! empty($settings->smtp_username)
            && ! empty($settings->smtp_password)
            && ! empty($settings->smtp_from_email);
    }

    private function mask(?string $value): ?string
    {
        return $value ? '••••••' : null;
    }

    private function sanitizeSensitivePayload(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '••••••') {
                unset($data[$field]);
            }
        }

        return $data;
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
