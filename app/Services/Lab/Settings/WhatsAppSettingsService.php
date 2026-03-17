<?php

namespace App\Services\Lab\Settings;

use App\Enums\WhatsAppLogAction;
use App\Enums\WhatsAppProvider;
use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class WhatsAppSettingsService
{
    private const DEFAULT_NOTIFICATIONS = [
        'new_case_alerts' => [
            'in_app_notification' => true,
            'email_notification' => false,
        ],
        'case_update_alerts' => [
            'in_app_notification' => true,
            'email_notification' => false,
        ],
    ];

    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    public function show(): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $settings = $this->settingsRepository->getOrCreateSettings($labId, [
            'notifications_json' => self::DEFAULT_NOTIFICATIONS,
        ]);

        $meta = $this->decryptJson($settings->whatsapp_meta_json);
        $twilio = $this->decryptJson($settings->whatsapp_twilio_json);

        $metaMasked = $this->maskTokens($meta, ['access_token']);
        $twilioMasked = $this->maskTokens($twilio, ['auth_token']);

        $logs = $this->settingsRepository->listWhatsappLogs($labId, 200);
        $lastLog = $logs->first();

        $lastMessageAt = $lastLog?->created_at?->toISOString();
        $totalSent24h = $logs->filter(function ($log) {
            return $log->created_at && $log->created_at->greaterThanOrEqualTo(now()->subDay());
        })->count();

        return ServiceResult::success([
            'provider' => $settings->whatsapp_provider,
            'webhook_url' => url('/api/lab/api/whatsapp/webhook'),
            'connection_status' => $settings->whatsapp_provider ? 'Connected' : 'Not Connected',
            'last_message_at' => $lastMessageAt,
            'total_sent_24h' => $totalSent24h,
            'meta' => $settings->whatsapp_provider === WhatsAppProvider::MetaCloudApi->value ? $metaMasked : null,
            'twilio' => $settings->whatsapp_provider === WhatsAppProvider::TwilioWhatsAppApi->value ? $twilioMasked : null,
        ], 'WhatsApp settings fetched successfully');
    }

    public function update(array $data): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $settings = $this->settingsRepository->getOrCreateSettings($labId, [
            'notifications_json' => self::DEFAULT_NOTIFICATIONS,
        ]);

        $provider = $data['provider'];
        $meta = $data['meta'] ?? null;
        $twilio = $data['twilio'] ?? null;

        if (is_array($meta) && isset($meta['access_token'])) {
            $meta['access_token'] = Crypt::encryptString((string) $meta['access_token']);
        }

        if (is_array($twilio) && isset($twilio['auth_token'])) {
            $twilio['auth_token'] = Crypt::encryptString((string) $twilio['auth_token']);
        }

        $metaEncrypted = $meta ? Crypt::encryptString(json_encode($meta, JSON_UNESCAPED_SLASHES)) : null;
        $twilioEncrypted = $twilio ? Crypt::encryptString(json_encode($twilio, JSON_UNESCAPED_SLASHES)) : null;

        $updated = $this->settingsRepository->updateSettings($settings, [
            'whatsapp_provider' => $provider,
            'whatsapp_meta_json' => $metaEncrypted,
            'whatsapp_twilio_json' => $twilioEncrypted,
        ]);

        $this->settingsRepository->createWhatsappLog([
            'lab_id' => $labId,
            'provider' => $provider,
            'action' => WhatsAppLogAction::SettingsUpdated->value,
            'details' => 'Provider set to ' . $provider . '.',
            'status' => 'Success',
            'created_at' => now(),
        ]);

        $metaMasked = $this->maskTokens($this->decryptJson($updated->whatsapp_meta_json), ['access_token']);
        $twilioMasked = $this->maskTokens($this->decryptJson($updated->whatsapp_twilio_json), ['auth_token']);

        return ServiceResult::success([
            'provider' => $updated->whatsapp_provider,
            'webhook_url' => url('/api/lab/api/whatsapp/webhook'),
            'meta' => $updated->whatsapp_provider === WhatsAppProvider::MetaCloudApi->value ? $metaMasked : null,
            'twilio' => $updated->whatsapp_provider === WhatsAppProvider::TwilioWhatsAppApi->value ? $twilioMasked : null,
        ], 'WhatsApp settings updated.');
    }

    public function testConnection(): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $settings = $this->settingsRepository->findSettingsByLab($labId);
        if (!$settings || !$settings->whatsapp_provider) {
            return ServiceResult::error('WhatsApp integration is not configured.', null, null, 422);
        }

        $log = $this->settingsRepository->createWhatsappLog([
            'lab_id' => $labId,
            'provider' => $settings->whatsapp_provider,
            'action' => WhatsAppLogAction::TestSent->value,
            'details' => 'Sent test to lab admin via ' . $settings->whatsapp_provider . '.',
            'status' => 'Success',
            'created_at' => now(),
        ]);

        return ServiceResult::success([
            'connection_status' => 'Success',
            'message' => 'Connection successful! Test message sent to lab admin.',
            'log_id' => (string) $log->id,
        ], 'WhatsApp test completed');
    }

    public function logs(): array
    {
        $labId = $this->currentLabId();
        if (!$labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $logs = $this->settingsRepository->listWhatsappLogs($labId, 100);

        $items = $logs->map(fn ($log) => [
            'id' => $log->id,
            'timestamp' => optional($log->created_at)->toISOString(),
            'action' => $log->action,
            'details' => $log->details,
            'status' => $log->status,
            'provider' => $log->provider,
        ])->values()->all();

        return ServiceResult::success($items, 'WhatsApp logs fetched successfully');
    }

    public function verifyWebhook(array $query): array
    {
        $mode = $query['hub_mode'] ?? $query['hub.mode'] ?? null;
        $token = $query['hub_verify_token'] ?? $query['hub.verify_token'] ?? null;
        $challenge = $query['hub_challenge'] ?? $query['hub.challenge'] ?? null;

        if ($mode !== 'subscribe' || !$token) {
            return ServiceResult::error('Invalid verification request', null, null, 400);
        }

        $settings = $this->findSettingsByVerifyToken($token);
        if (!$settings) {
            return ServiceResult::error('Verification token mismatch', null, null, 403);
        }

        return ServiceResult::success(['challenge' => (string) $challenge], 'Webhook verified');
    }

    public function handleWebhook(array $payload): array
    {
        $settings = $this->findSettingsByPayload($payload);
        if ($settings) {
            $this->settingsRepository->createWhatsappLog([
                'lab_id' => $settings->lab_id,
                'provider' => $settings->whatsapp_provider ?? WhatsAppProvider::MetaCloudApi->value,
                'action' => WhatsAppLogAction::WebhookReceived->value,
                'details' => 'Webhook received.',
                'status' => 'Success',
                'created_at' => now(),
            ]);
        }

        return ServiceResult::success(null, 'Webhook received');
    }

    private function findSettingsByVerifyToken(string $token)
    {
        $settings = $this->settingsRepository->listAllSettings();

        foreach ($settings as $setting) {
            $meta = $this->decryptJson($setting->whatsapp_meta_json);
            if (is_array($meta) && Arr::get($meta, 'verify_token') === $token) {
                return $setting;
            }
        }

        return null;
    }

    private function findSettingsByPayload(array $payload)
    {
        $settings = $this->settingsRepository->listAllSettings();

        $metaAccountId = data_get($payload, 'entry.0.id');
        $twilioAccountSid = $payload['AccountSid'] ?? $payload['account_sid'] ?? null;

        foreach ($settings as $setting) {
            if ($setting->whatsapp_provider === WhatsAppProvider::MetaCloudApi->value) {
                $meta = $this->decryptJson($setting->whatsapp_meta_json);
                if (is_array($meta) && $metaAccountId && Arr::get($meta, 'whatsapp_business_account_id') === $metaAccountId) {
                    return $setting;
                }
            }

            if ($setting->whatsapp_provider === WhatsAppProvider::TwilioWhatsAppApi->value) {
                $twilio = $this->decryptJson($setting->whatsapp_twilio_json);
                if (is_array($twilio) && $twilioAccountSid && Arr::get($twilio, 'account_sid') === $twilioAccountSid) {
                    return $setting;
                }
            }
        }

        return null;
    }

    private function decryptJson(?string $encrypted): ?array
    {
        if (!$encrypted) {
            return null;
        }

        try {
            $json = Crypt::decryptString($encrypted);
            $data = json_decode($json, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function maskTokens(?array $data, array $keys): ?array
    {
        if (!$data) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '********';
            }
        }

        return $data;
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
