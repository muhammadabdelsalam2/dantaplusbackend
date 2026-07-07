<?php

namespace App\Jobs\Clinic;

use App\Models\Clinic\MessageLog;
use App\Services\Clinic\WhatsappBot\Providers\WhatsAppProviderInterface;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPatientWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageLogId)
    {
    }

    public function handle(WhatsAppProviderInterface $whatsAppProvider, SmsProviderInterface $smsProvider): void
    {
        $log = MessageLog::query()
            ->with('message.clinic')
            ->find($this->messageLogId);

        if (! $log) {
            return;
        }

        try {
            if (! $log->phone) {
                $log->update(['status' => 'failed']);
                return;
            }

            $result = match ($log->channel) {
                'whatsapp' => $whatsAppProvider->sendMessage($log->phone, $log->message_body, $log->message?->clinic),
                'sms' => $smsProvider->sendMessage($log->phone, $log->message_body, [
                    'clinic_id' => $log->clinic_id,
                    'message_log_id' => $log->id,
                    'message_type' => $log->message_type,
                ]),
                default => ['success' => false, 'error' => 'Unsupported message channel.'],
            };

            $log->update([
                'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
            ]);
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed']);

            Log::error('Failed to send patient WhatsApp message.', [
                'message_log_id' => $this->messageLogId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
