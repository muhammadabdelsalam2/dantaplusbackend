<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\FeedbackLogResource;
use App\Http\Resources\Clinic\Settings\FeedbackSettingsResource;
use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\FeedbackLog;
use App\Models\FeedbackSetting;
use App\Support\ServiceResult;

class ClinicFeedbackSettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new FeedbackSettingsResource($this->settingForClinic($clinicId)))->resolve(),
            'Feedback settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinicId);
        $setting->fill([
            'is_enabled' => $data['enabled'] ?? $setting->is_enabled,
            'channels' => $data['channels'] ?? $setting->channels,
            'delay_minutes' => $data['delay_minutes'] ?? $setting->delay_minutes,
            'message_template' => $data['message_template'] ?? $setting->message_template,
            'custom_link' => array_key_exists('custom_link', $data) ? $data['custom_link'] : $setting->custom_link,
            'updated_by' => auth()->id(),
        ]);
        if (! $setting->exists) {
            $setting->created_by = auth()->id();
        }
        $setting->save();

        return ServiceResult::success(
            (new FeedbackSettingsResource($setting->fresh()))->resolve(),
            'Feedback settings updated successfully'
        );
    }

    public function logs(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $logs = FeedbackLog::query()
            ->with(['patient.user:id,name', 'appointment'])
            ->where('clinic_id', $clinicId)
            ->latest('scheduled_for')
            ->limit(100)
            ->get();

        return ServiceResult::success(
            FeedbackLogResource::collection($logs)->resolve(),
            'Feedback logs fetched successfully'
        );
    }

    public function scheduleFeedbackForAppointment(ClinicAppointment $appointment): void
    {
        $setting = $this->settingForClinic($appointment->clinic_id);
        if (! $setting->is_enabled) {
            return;
        }

        $clinic = $appointment->clinic()->first();
        $patientName = $appointment->patient?->user?->name ?? $appointment->patient_name;
        $feedbackLink = $setting->custom_link ?: $this->generatedFeedbackLink($appointment);
        $scheduledFor = now()->addMinutes($setting->delay_minutes);

        foreach ($setting->channels ?: ['sms'] as $channel) {
            FeedbackLog::query()->updateOrCreate(
                [
                    'clinic_id' => $appointment->clinic_id,
                    'clinic_appointment_id' => $appointment->id,
                    'channel' => $channel,
                ],
                [
                    'patient_id' => $appointment->patient_id,
                    'message_template' => $setting->message_template,
                    'rendered_message' => $this->renderTemplate(
                        $setting->message_template,
                        $patientName,
                        $clinic?->name ?? 'Clinic',
                        $feedbackLink
                    ),
                    'feedback_link' => $feedbackLink,
                    'status' => 'pending',
                    'scheduled_for' => $scheduledFor,
                    'payload' => [
                        'appointment_at' => optional($appointment->appointment_at)?->toISOString(),
                    ],
                ]
            );
        }
    }

    public function processDueFeedbackLogs(): void
    {
        FeedbackLog::query()
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->get()
            ->each(function (FeedbackLog $log): void {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                logger()->info('Feedback notification simulated.', [
                    'feedback_log_id' => $log->id,
                    'clinic_id' => $log->clinic_id,
                    'appointment_id' => $log->clinic_appointment_id,
                ]);
            });
    }

    private function settingForClinic(int $clinicId): FeedbackSetting
    {
        return FeedbackSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'is_enabled' => false,
                'channels' => ['sms'],
                'delay_minutes' => 5,
                'message_template' => "Hello {PatientName},\nThank you for visiting {ClinicName}! Please share your feedback here: {FeedbackLink}",
            ]
        );
    }

    private function renderTemplate(string $template, string $patientName, string $clinicName, string $feedbackLink): string
    {
        return str_replace(
            ['{PatientName}', '{ClinicName}', '{FeedbackLink}'],
            [$patientName, $clinicName, $feedbackLink],
            $template
        );
    }

    private function generatedFeedbackLink(ClinicAppointment $appointment): string
    {
        return url('/feedback/' . $appointment->id);
    }
}
