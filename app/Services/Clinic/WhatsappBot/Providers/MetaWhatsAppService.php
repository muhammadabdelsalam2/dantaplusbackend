<?php

namespace App\Services\Clinic\WhatsappBot\Providers;

use App\Models\Clinic;
use App\Models\Clinic\CommunicationSettings;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppService implements WhatsAppProviderInterface
{
    public function sendMessage(string $to, string $message, ?Clinic $clinic = null): array
    {
        $credentials = $this->credentials($clinic);

        if (! $credentials['token'] || ! $credentials['phone_id']) {
            return [
                'success' => false,
                'provider' => 'meta',
                'message' => 'Meta WhatsApp credentials are not configured.',
            ];
        }

        $response = Http::timeout(20)
            ->withToken($credentials['token'])
            ->post($credentials['base_url'] . '/' . $credentials['phone_id'] . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        return [
            'success' => $response->successful(),
            'provider' => 'meta',
            'status_code' => $response->status(),
            'response' => $response->json(),
        ];
    }

    private function credentials(?Clinic $clinic): array
    {
        $settings = $clinic
            ? CommunicationSettings::query()->where('clinic_id', $clinic->id)->first()
            : null;

        return [
            'base_url' => rtrim((string) config('services.whatsapp.meta.base_url'), '/'),
            'token' => $settings?->whatsapp_access_token ?: config('services.whatsapp.token'),
            'phone_id' => $settings?->whatsapp_phone_number_id ?: config('services.whatsapp.phone_id'),
        ];
    }
}
