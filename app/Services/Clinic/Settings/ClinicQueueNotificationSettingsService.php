<?php

namespace App\Services\Clinic\Settings;

use App\Enums\WhatsAppProvider;
use App\Http\Resources\Clinic\Settings\QueueNotificationSettingsResource;
use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\QueueNotificationSetting;
use App\Support\ServiceResult;

class ClinicQueueNotificationSettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new QueueNotificationSettingsResource($this->settingForClinic($clinicId)))->resolve(),
            'Queue notification settings fetched successfully'
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
            'notify_next' => $data['notify_next'] ?? $setting->notify_next,
            'whatsapp_provider' => $data['whatsapp_provider'] ?? $setting->whatsapp_provider,
            'message_template' => $data['message_template'] ?? $setting->message_template,
            'updated_by' => auth()->id(),
        ]);
        if (! $setting->exists) {
            $setting->created_by = auth()->id();
        }
        $setting->save();

        return ServiceResult::success(
            (new QueueNotificationSettingsResource($setting->fresh()))->resolve(),
            'Queue notification settings updated successfully'
        );
    }

    public function test(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinicId);
        $clinic = Clinic::query()->find($clinicId);

        $appointment = ! empty($data['appointment_id'])
            ? ClinicAppointment::query()->where('clinic_id', $clinicId)->find($data['appointment_id'])
            : ClinicAppointment::query()->where('clinic_id', $clinicId)->orderBy('appointment_at')->first();

        $patientName = $data['patient_name']
            ?? $appointment?->patient?->user?->name
            ?? $appointment?->patient_name
            ?? 'Patient';

        $rendered = $this->renderTemplate(
            $setting->message_template,
            $patientName,
            (int) ($data['number_before'] ?? $setting->notify_next),
            $clinic?->name ?? 'Clinic',
            $appointment?->appointment_at?->format('H:i') ?? '--:--'
        );

        return ServiceResult::success([
            'status' => 'queued',
            'provider' => $setting->whatsapp_provider,
            'rendered_message' => $rendered,
        ], 'Queue notification test queued successfully');
    }

    public function notifyUpcomingPatients(ClinicAppointment $arrivedAppointment): void
    {
        $setting = $this->settingForClinic($arrivedAppointment->clinic_id);
        if (! $setting->is_enabled) {
            return;
        }

        $clinic = $arrivedAppointment->clinic()->first();
        $upcoming = ClinicAppointment::query()
            ->with('patient.user:id,name')
            ->where('clinic_id', $arrivedAppointment->clinic_id)
            ->whereDate('appointment_at', $arrivedAppointment->appointment_at?->toDateString())
            ->where('appointment_at', '>', $arrivedAppointment->appointment_at)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('appointment_at')
            ->limit($setting->notify_next)
            ->get();

        foreach ($upcoming as $index => $appointment) {
            $rendered = $this->renderTemplate(
                $setting->message_template,
                $appointment->patient?->user?->name ?? $appointment->patient_name,
                $index + 1,
                $clinic?->name ?? 'Clinic',
                $appointment->appointment_at?->format('H:i') ?? '--:--'
            );

            logger()->info('Queue notification simulated.', [
                'appointment_id' => $appointment->id,
                'clinic_id' => $appointment->clinic_id,
                'provider' => $setting->whatsapp_provider,
                'message' => $rendered,
            ]);
        }
    }

    private function settingForClinic(int $clinicId): QueueNotificationSetting
    {
        return QueueNotificationSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'is_enabled' => false,
                'notify_next' => 3,
                'whatsapp_provider' => WhatsAppProvider::TwilioWhatsAppApi->value,
                'message_template' => 'Dear {PatientName}, there are only {numberBefore} patients before you at {ClinicName}.',
            ]
        );
    }

    private function renderTemplate(string $template, string $patientName, int $numberBefore, string $clinicName, string $appointmentTime): string
    {
        return str_replace(
            ['{PatientName}', '{numberBefore}', '{ClinicName}', '{AppointmentTime}'],
            [$patientName, (string) $numberBefore, $clinicName, $appointmentTime],
            $template
        );
    }
}
