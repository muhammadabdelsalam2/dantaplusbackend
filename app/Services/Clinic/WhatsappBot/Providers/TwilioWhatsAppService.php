<?php

namespace App\Services\Clinic\WhatsappBot\Providers;

use App\Models\Clinic;
use Illuminate\Support\Facades\Http;

class TwilioWhatsAppService implements WhatsAppProviderInterface
{
    public function sendMessage(string $to, string $message, ?Clinic $clinic = null): array
    {
        $accountSid = (string) config('services.whatsapp.twilio.account_sid');
        $authToken = (string) config('services.whatsapp.twilio.auth_token');
        $from = (string) config('services.whatsapp.twilio.from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'message' => 'Twilio WhatsApp credentials are not configured.',
            ];
        }

        $response = Http::asForm()
            ->timeout(20)
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $this->prefixWhatsApp($from),
                'To' => $this->prefixWhatsApp($to),
                'Body' => $message,
            ]);

        return [
            'success' => $response->successful(),
            'provider' => 'twilio',
            'status_code' => $response->status(),
            'response' => $response->json(),
        ];
    }

    private function prefixWhatsApp(string $value): string
    {
        return str_starts_with($value, 'whatsapp:') ? $value : 'whatsapp:' . $value;
    }
}
