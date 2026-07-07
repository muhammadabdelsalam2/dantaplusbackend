<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class ConfiguredHttpSmsProvider implements SmsProviderInterface
{
    public function sendMessage(string $to, string $message, array $context = []): array
    {
        $endpoint = config('services.sms.endpoint');
        $apiKey = config('services.sms.api_key');
        $sender = config('services.sms.sender');

        if (! $endpoint || ! $apiKey || ! $sender) {
            return [
                'success' => false,
                'provider' => 'configured_http_sms',
                'error' => 'SMS gateway is not configured. Set SMS_ENDPOINT, SMS_API_KEY, and SMS_SENDER in the environment.',
            ];
        }

        $response = Http::asJson()
            ->timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])
            ->post($endpoint, [
                'to' => $to,
                'message' => $message,
                'sender' => $sender,
                'context' => $context,
            ]);

        return [
            'success' => $response->successful(),
            'provider' => 'configured_http_sms',
            'status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }
}

