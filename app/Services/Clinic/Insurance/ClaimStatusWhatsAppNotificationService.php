<?php

namespace App\Services\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\WhatsAppMessage;
use Exception;
use Illuminate\Support\Facades\Log;

class ClaimStatusWhatsAppNotificationService
{
    private ?string $patientPhone = null;

    public function __construct(private InsuranceClaim $claim)
    {
        $this->patientPhone = $claim->patient?->user?->phone;
    }

    /**
     * Send WhatsApp notification based on status change
     */
    public function sendNotification(): bool
    {
        if (!$this->patientPhone) {
            Log::warning('WhatsApp notification skipped: Patient phone not found', [
                'claim_id' => $this->claim->id,
            ]);
            return false;
        }

        $message = match ($this->claim->status) {
            InsuranceClaim::STATUS_APPROVED => $this->getApprovedMessage(),
            InsuranceClaim::STATUS_REJECTED => $this->getRejectedMessage(),
            InsuranceClaim::STATUS_PARTIALLY_APPROVED => $this->getPartiallyApprovedMessage(),
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT => $this->getApprovedWithLimitMessage(),
            default => null,
        };

        if (!$message) {
            return false;
        }

        return $this->send($message);
    }

    private function getApprovedMessage(): string
    {
        $portalUrl = config('app.patient_portal_url') ?? config('app.url');
        return "تمت الموافقة على مطالبتك رقم {$this->claim->claim_number} من شركة التأمين. يمكنك الدخول إلى بوابة المرضى: {$portalUrl}";
    }

    private function getRejectedMessage(): string
    {
        return "نأسف، تم رفض مطالبتك رقم {$this->claim->claim_number}. برجاء التواصل مع العيادة";
    }

    private function getPartiallyApprovedMessage(): string
    {
        $portalUrl = config('app.patient_portal_url') ?? config('app.url');
        $patientDue = $this->claim->gross_amount - $this->claim->approved_amount;
        return "تمت الموافقة الجزئية على مطالبتك رقم {$this->claim->claim_number}. المبلغ المعتمد: {$this->claim->approved_amount}، المبلغ المستحق منك: {$patientDue}. يمكنك الدخول إلى بوابة المرضى: {$portalUrl}";
    }

    private function getApprovedWithLimitMessage(): string
    {
        $portalUrl = config('app.patient_portal_url') ?? config('app.url');
        $patientDue = $this->claim->gross_amount - $this->claim->approved_amount;
        return "تمت الموافقة على مطالبتك رقم {$this->claim->claim_number} بحد أقصى. المبلغ المعتمد: {$this->claim->approved_amount}، المبلغ المستحق منك: {$patientDue}. يمكنك الدخول إلى بوابة المرضى: {$portalUrl}";
    }

    private function send(string $message): bool
    {
        try {
            // Get WhatsApp provider from config or service
            $provider = app('whatsapp.provider');
            if (!$provider) {
                Log::warning('WhatsApp provider not configured');
                return false;
            }

            // Send message through provider
            $result = $provider->send(
                phone: $this->patientPhone,
                message: $message
            );

            // Log success
            if (class_exists(WhatsAppMessage::class)) {
                WhatsAppMessage::create([
                    'clinic_id' => $this->claim->clinic_id,
                    'to' => $this->patientPhone,
                    'message' => $message,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'related_type' => 'insurance_claim',
                    'related_id' => $this->claim->id,
                    'action' => 'claim_status_update',
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('WhatsApp notification failed', [
                'claim_id' => $this->claim->id,
                'error' => $e->getMessage(),
            ]);

            // Log failure (non-blocking)
            if (class_exists(WhatsAppMessage::class)) {
                WhatsAppMessage::create([
                    'clinic_id' => $this->claim->clinic_id,
                    'to' => $this->patientPhone,
                    'message' => $message,
                    'status' => 'failed',
                    'sent_at' => now(),
                    'related_type' => 'insurance_claim',
                    'related_id' => $this->claim->id,
                    'action' => 'claim_status_update',
                ]);
            }

            return false;
        }
    }
}
