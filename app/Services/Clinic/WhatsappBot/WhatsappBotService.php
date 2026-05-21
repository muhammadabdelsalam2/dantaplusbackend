<?php

namespace App\Services\Clinic\WhatsappBot;

use App\Http\Resources\Clinic\Settings\WhatsappBotSettingResource;
use App\Models\Clinic;
use App\Models\Clinic\CommunicationSettings;
use App\Models\Patient;
use App\Models\WhatsappBotSetting;
use App\Models\WhatsappMessage;
use App\Services\Clinic\WhatsappBot\Providers\WhatsAppProviderInterface;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class WhatsappBotService
{
    public function __construct(
        private IntentDetectorService $intentDetectorService,
        private AIReplyService $aiReplyService,
        private WhatsAppProviderInterface $provider,
    ) {
    }

    public function index(): array
    {
        $clinic = $this->currentClinic();
        if (! $clinic) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new WhatsappBotSettingResource($this->settingForClinic($clinic->id)))->resolve(),
            'WhatsApp bot settings fetched successfully.'
        );
    }

    public function update(array $data): array
    {
        $clinic = $this->currentClinic();
        if (! $clinic) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinic->id);
        $setting->fill($this->normalizeSettingsPayload($data));
        $setting->save();

        return ServiceResult::success(
            (new WhatsappBotSettingResource($setting->fresh()))->resolve(),
            'WhatsApp bot settings updated successfully.'
        );
    }

    public function toggle(bool $enabled): array
    {
        $clinic = $this->currentClinic();
        if (! $clinic) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinic->id);
        $setting->update(['is_enabled' => $enabled]);

        return ServiceResult::success(
            (new WhatsappBotSettingResource($setting->fresh()))->resolve(),
            $enabled ? 'WhatsApp bot enabled successfully.' : 'WhatsApp bot disabled successfully.'
        );
    }

    public function simulate(string $message): array
    {
        $clinic = $this->currentClinic();
        if (! $clinic) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            $this->generateReply($message, $clinic),
            'WhatsApp bot simulation completed successfully.'
        );
    }

    public function handleWebhook(array $payload): array
    {
        $incoming = $this->parseIncomingPayload($payload);

        if (! $incoming['message'] || ! $incoming['patient_phone']) {
            return ServiceResult::error('Incoming WhatsApp message payload is invalid.', null, [
                'payload' => ['A message body and patient phone are required.'],
            ], 422);
        }

        $clinic = $this->resolveClinicFromPayload($payload, $incoming['patient_phone']);
        if (! $clinic) {
            return ServiceResult::error('Unable to resolve clinic for the incoming WhatsApp message.', null, null, 404);
        }

        $setting = $this->settingForClinic($clinic->id);

        if (! $setting->is_enabled) {
            $this->logMessage($clinic->id, $incoming['patient_phone'], $incoming['message'], null, 'BOT_DISABLED');

            return ServiceResult::success([
                'processed' => false,
                'clinic_id' => $clinic->id,
            ], 'WhatsApp bot is disabled for this clinic.');
        }

        $replyPayload = $this->generateReply($incoming['message'], $clinic);
        $providerResult = $this->provider->sendMessage($incoming['patient_phone'], $replyPayload['reply'], $clinic);

        $this->logMessage(
            $clinic->id,
            $incoming['patient_phone'],
            $incoming['message'],
            $replyPayload['reply'],
            $replyPayload['intent']
        );

        return ServiceResult::success([
            'processed' => true,
            'clinic_id' => $clinic->id,
            'provider' => $providerResult['provider'] ?? config('services.whatsapp.provider'),
            'provider_success' => (bool) ($providerResult['success'] ?? false),
            'reply' => $replyPayload['reply'],
            'intent' => $replyPayload['intent'],
        ], 'WhatsApp webhook processed successfully.');
    }

    public function generateReply(string $message, Clinic $clinic): array
    {
        $setting = $this->settingForClinic($clinic->id);
        $language = $this->detectLanguage($message, $setting);
        $intent = $this->detectIntent($message, $clinic);

        if (! $this->checkWorkingHours($setting)) {
            return [
                'reply' => $this->formatMessage(
                    $setting->out_of_hours_message ?: $this->defaultOutOfHoursMessage($language),
                    $clinic,
                    $setting
                ),
                'intent' => $intent,
                'language' => $language,
                'source' => 'rules',
            ];
        }

        if ($setting->ai_enabled) {
            try {
                return [
                    'reply' => $this->aiReplyService->generateReply($message, $clinic, $setting, $intent, $language),
                    'intent' => $intent,
                    'language' => $language,
                    'source' => 'ai',
                ];
            } catch (\Throwable) {
                // Fall back to rule-based replies when AI is unavailable.
            }
        }

        return [
            'reply' => $this->buildRuleBasedReply($intent, $clinic, $setting, $language),
            'intent' => $intent,
            'language' => $language,
            'source' => 'rules',
        ];
    }

    public function checkWorkingHours(WhatsappBotSetting $setting): bool
    {
        if (! $setting->start_time || ! $setting->end_time) {
            return true;
        }

        $now = Carbon::now();
        $start = Carbon::createFromFormat('H:i:s', $this->normalizeTime($setting->start_time), $now->timezone)
            ->setDate($now->year, $now->month, $now->day);
        $end = Carbon::createFromFormat('H:i:s', $this->normalizeTime($setting->end_time), $now->timezone)
            ->setDate($now->year, $now->month, $now->day);

        if ($end->lessThan($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }

        return $now->betweenIncluded($start, $end);
    }

    public function detectLanguage(string $message, ?WhatsappBotSetting $setting = null): string
    {
        $preference = $setting?->language ?? 'auto';

        if (in_array($preference, ['ar', 'en'], true)) {
            return $preference;
        }

        return preg_match('/[\x{0600}-\x{06FF}]/u', $message) === 1 ? 'ar' : 'en';
    }

    public function formatMessage(string $template, Clinic $clinic, ?WhatsappBotSetting $setting = null, array $context = []): string
    {
        $setting ??= $this->settingForClinic($clinic->id);

        return str_replace(
            ['{ClinicName}', '{StartTime}', '{EndTime}', '{DepositAmount}', '{AllowedServices}', '{PatientName}'],
            [
                $clinic->name,
                $this->displayTime($setting->start_time),
                $this->displayTime($setting->end_time),
                $setting->deposit_amount !== null ? number_format((float) $setting->deposit_amount, 2) : '0.00',
                implode(', ', $setting->allowed_services ?? []),
                $context['patient_name'] ?? 'Patient',
            ],
            $template
        );
    }

    public function detectIntent(string $message, Clinic $clinic): string
    {
        return $this->intentDetectorService->detect(
            $message,
            $clinic,
            $this->settingForClinic($clinic->id)
        );
    }

    private function buildRuleBasedReply(
        string $intent,
        Clinic $clinic,
        WhatsappBotSetting $setting,
        string $language
    ): string {
        return match ($intent) {
            IntentDetectorService::GREETING => $this->formatMessage(
                $setting->welcome_message ?: $this->defaultWelcomeMessage($language),
                $clinic,
                $setting
            ),
            IntentDetectorService::BOOK_APPOINTMENT => $this->bookingReply($clinic, $setting, $language),
            IntentDetectorService::CANCEL_APPOINTMENT => $this->cancelReply($clinic, $language),
            IntentDetectorService::RESCHEDULE => $this->rescheduleReply($clinic, $language),
            default => $this->inquiryReply($clinic, $setting, $language),
        };
    }

    private function bookingReply(Clinic $clinic, WhatsappBotSetting $setting, string $language): string
    {
        $services = implode(', ', $setting->allowed_services ?? []);
        $depositNote = $setting->require_deposit
            ? ($language === 'ar'
                ? ' يتطلب الحجز عربونًا بقيمة ' . number_format((float) $setting->deposit_amount, 2) . '.'
                : ' A deposit of ' . number_format((float) $setting->deposit_amount, 2) . ' is required to confirm the booking.')
            : '';

        if ($language === 'ar') {
            $message = 'يسعدنا مساعدتك في حجز موعد في {ClinicName}.';
            if ($services !== '') {
                $message .= ' الخدمات المتاحة للحجز عبر البوت: {AllowedServices}.';
            }
            $message .= ' برجاء إرسال الخدمة المطلوبة واليوم أو الوقت المناسب لك.' . $depositNote;

            return $this->formatMessage($message, $clinic, $setting);
        }

        $message = 'We can help you book an appointment at {ClinicName}.';
        if ($services !== '') {
            $message .= ' Available services for bot booking: {AllowedServices}.';
        }
        $message .= ' Please share the service you need and your preferred day or time.' . $depositNote;

        return $this->formatMessage($message, $clinic, $setting);
    }

    private function cancelReply(Clinic $clinic, string $language): string
    {
        return $language === 'ar'
            ? $this->formatMessage('سنساعدك في إلغاء الموعد في {ClinicName}. برجاء إرسال اسم المريض وموعد الحجز أو رقم الهاتف للتأكيد.', $clinic)
            : $this->formatMessage('We can help cancel your appointment at {ClinicName}. Please send the patient name, appointment time, or phone number for confirmation.', $clinic);
    }

    private function rescheduleReply(Clinic $clinic, string $language): string
    {
        return $language === 'ar'
            ? $this->formatMessage('يمكننا مساعدتك في إعادة جدولة الموعد في {ClinicName}. أرسل الموعد الحالي والوقت الجديد المناسب لك.', $clinic)
            : $this->formatMessage('We can help reschedule your appointment at {ClinicName}. Please send your current appointment details and the new preferred time.', $clinic);
    }

    private function inquiryReply(Clinic $clinic, WhatsappBotSetting $setting, string $language): string
    {
        if ($language === 'ar') {
            $message = 'شكرًا لتواصلك مع {ClinicName}. يمكنني مساعدتك في الحجز أو الإلغاء أو إعادة الجدولة أو الإجابة عن الاستفسارات العامة.';
            if (($setting->allowed_services ?? []) !== []) {
                $message .= ' الخدمات المتاحة حاليًا: {AllowedServices}.';
            }

            return $this->formatMessage($message, $clinic, $setting);
        }

        $message = 'Thanks for contacting {ClinicName}. I can help with booking, cancellation, rescheduling, or general questions.';
        if (($setting->allowed_services ?? []) !== []) {
            $message .= ' Current available services: {AllowedServices}.';
        }

        return $this->formatMessage($message, $clinic, $setting);
    }

    private function parseIncomingPayload(array $payload): array
    {
        $metaMessage = data_get($payload, 'entry.0.changes.0.value.messages.0.text.body');
        $metaPhone = data_get($payload, 'entry.0.changes.0.value.messages.0.from');

        $twilioMessage = $payload['Body'] ?? null;
        $twilioPhone = $payload['From'] ?? null;

        return [
            'message' => is_string($metaMessage) && $metaMessage !== '' ? $metaMessage : $twilioMessage,
            'patient_phone' => $this->normalizePhone(is_string($metaPhone) && $metaPhone !== '' ? $metaPhone : $twilioPhone),
        ];
    }

    private function resolveClinicFromPayload(array $payload, string $patientPhone): ?Clinic
    {
        if (! empty($payload['clinic_id'])) {
            return Clinic::query()->find((int) $payload['clinic_id']);
        }

        $phoneId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        if ($phoneId) {
            $settings = CommunicationSettings::query()
                ->where('whatsapp_phone_number_id', (string) $phoneId)
                ->first();

            if ($settings) {
                return Clinic::query()->find($settings->clinic_id);
            }
        }

        $businessAccountId = data_get($payload, 'entry.0.id');
        if ($businessAccountId) {
            $settings = CommunicationSettings::query()
                ->where('whatsapp_business_account_id', (string) $businessAccountId)
                ->first();

            if ($settings) {
                return Clinic::query()->find($settings->clinic_id);
            }
        }

        $clinicIds = Patient::query()
            ->where(function (Builder $query) use ($patientPhone) {
                $query->where('phone', $patientPhone)
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('phone', $patientPhone));
            })
            ->pluck('clinic_id')
            ->unique()
            ->values();

        if ($clinicIds->count() === 1) {
            return Clinic::query()->find((int) $clinicIds->first());
        }

        return null;
    }

    private function logMessage(int $clinicId, string $patientPhone, string $message, ?string $reply, ?string $intent): void
    {
        WhatsappMessage::query()->create([
            'clinic_id' => $clinicId,
            'patient_phone' => $patientPhone,
            'message' => $message,
            'reply' => $reply,
            'intent' => $intent,
            'created_at' => now(),
        ]);
    }

    private function settingForClinic(int $clinicId): WhatsappBotSetting
    {
        return WhatsappBotSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'is_enabled' => false,
                'welcome_message' => 'Hello! Welcome to {ClinicName}. How can I help you today?',
                'out_of_hours_message' => 'Thank you for your message. Our working hours are from {StartTime} to {EndTime}. We will get back to you as soon as possible.',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'language' => 'auto',
                'require_deposit' => false,
                'deposit_amount' => null,
                'allowed_services' => [],
                'ai_enabled' => false,
            ]
        );
    }

    private function normalizeSettingsPayload(array $data): array
    {
        if (array_key_exists('allowed_services', $data)) {
            $data['allowed_services'] = array_values(array_unique(array_filter(
                $data['allowed_services'] ?? [],
                static fn ($service) => is_string($service) && trim($service) !== ''
            )));
        }

        if (($data['require_deposit'] ?? null) === false) {
            $data['deposit_amount'] = null;
        }

        return $data;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $normalized = str_replace('whatsapp:', '', trim($phone));
        $normalized = preg_replace('/[^\d+]/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function displayTime(?string $time): string
    {
        if (! $time) {
            return '--:--';
        }

        return Carbon::createFromFormat('H:i:s', $this->normalizeTime($time))->format('H:i');
    }

    private function defaultWelcomeMessage(string $language): string
    {
        return $language === 'ar'
            ? 'مرحبًا بك في {ClinicName}. كيف يمكنني مساعدتك اليوم؟'
            : 'Hello! Welcome to {ClinicName}. How can I help you today?';
    }

    private function defaultOutOfHoursMessage(string $language): string
    {
        return $language === 'ar'
            ? 'شكرًا لرسالتك. ساعات العمل لدينا من {StartTime} إلى {EndTime}. سنرد عليك في أقرب وقت ممكن.'
            : 'Thank you for your message. Our working hours are from {StartTime} to {EndTime}. We will get back to you as soon as possible.';
    }

    private function currentClinic(): ?Clinic
    {
        $clinicId = auth()->user()?->clinic_id;

        return $clinicId ? Clinic::query()->find($clinicId) : null;
    }
}
