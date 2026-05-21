<?php

namespace App\Services\Clinic\WhatsappBot;

use App\Models\Clinic;
use App\Models\WhatsappBotSetting;

class IntentDetectorService
{
    public const BOOK_APPOINTMENT = 'BOOK_APPOINTMENT';
    public const CANCEL_APPOINTMENT = 'CANCEL_APPOINTMENT';
    public const RESCHEDULE = 'RESCHEDULE';
    public const INQUIRY = 'INQUIRY';
    public const GREETING = 'GREETING';

    private const SUPPORTED_INTENTS = [
        self::BOOK_APPOINTMENT,
        self::CANCEL_APPOINTMENT,
        self::RESCHEDULE,
        self::INQUIRY,
        self::GREETING,
    ];

    public function __construct(private AIReplyService $aiReplyService)
    {
    }

    public function detect(string $message, Clinic $clinic, WhatsappBotSetting $setting): string
    {
        if ($setting->ai_enabled) {
            $intent = $this->detectWithAi($message);

            if ($intent !== null) {
                return $intent;
            }
        }

        return $this->detectWithRules($message);
    }

    private function detectWithAi(string $message): ?string
    {
        try {
            $intent = strtoupper(trim($this->aiReplyService->detectIntent($message)));

            return in_array($intent, self::SUPPORTED_INTENTS, true) ? $intent : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectWithRules(string $message): string
    {
        $normalized = mb_strtolower(trim($message));

        if ($this->matches($normalized, [
            '/\b(cancel|cancellation)\b/u',
            '/\bالغاء\b/u',
            '/\bإلغاء\b/u',
            '/\bالغي\b/u',
        ])) {
            return self::CANCEL_APPOINTMENT;
        }

        if ($this->matches($normalized, [
            '/\b(reschedule|change appointment|move appointment|another time)\b/u',
            '/\bتغيير الموعد\b/u',
            '/\bتعديل الموعد\b/u',
            '/\bتاجيل\b/u',
            '/\bتأجيل\b/u',
        ])) {
            return self::RESCHEDULE;
        }

        if ($this->matches($normalized, [
            '/\b(book|booking|appointment|reserve)\b/u',
            '/\bحجز\b/u',
            '/\bموعد\b/u',
            '/\bاكشف\b/u',
            '/\bكشف\b/u',
        ])) {
            return self::BOOK_APPOINTMENT;
        }

        if ($this->matches($normalized, [
            '/^(hi|hello|hey|good morning|good evening)\b/u',
            '/^(مرحبا|السلام|اهلا|أهلا|هاي)\b/u',
        ])) {
            return self::GREETING;
        }

        return self::INQUIRY;
    }

    private function matches(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
