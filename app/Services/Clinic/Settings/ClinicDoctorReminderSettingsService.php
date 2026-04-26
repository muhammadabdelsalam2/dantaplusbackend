<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\DoctorReminderLogResource;
use App\Http\Resources\Clinic\Settings\DoctorReminderSettingsResource;
use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\DoctorReminderLog;
use App\Models\DoctorReminderSetting;
use App\Models\User;
use App\Support\ServiceResult;
use Carbon\Carbon;

class ClinicDoctorReminderSettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new DoctorReminderSettingsResource($this->settingForClinic($clinicId)))->resolve(),
            'Doctor reminder settings fetched successfully'
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
            'send_time' => $data['send_time'] ?? $setting->send_time,
            'channels' => $data['channels'] ?? $setting->channels,
            'message_template' => $data['message_template'] ?? $setting->message_template,
            'updated_by' => auth()->id(),
        ]);
        if (! $setting->exists) {
            $setting->created_by = auth()->id();
        }
        $setting->save();

        return ServiceResult::success(
            (new DoctorReminderSettingsResource($setting->fresh()))->resolve(),
            'Doctor reminder settings updated successfully'
        );
    }

    public function triggerForClinic(?int $clinicId, ?string $date = null, ?int $createdBy = null): array
    {
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinicId);
        $targetDate = $date ? Carbon::parse($date)->startOfDay() : now()->addDay()->startOfDay();
        $clinic = Clinic::query()->find($clinicId);

        $appointments = ClinicAppointment::query()
            ->with(['doctor:id,name', 'patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_at', $targetDate->toDateString())
            ->whereNotNull('doctor_user_id')
            ->orderBy('doctor_user_id')
            ->orderBy('appointment_at')
            ->get()
            ->groupBy('doctor_user_id');

        $logs = [];

        foreach ($appointments as $doctorUserId => $doctorAppointments) {
            $doctor = $doctorAppointments->first()?->doctor;
            if (! $doctor instanceof User) {
                continue;
            }

            foreach ($setting->channels ?: ['sms', 'whatsapp'] as $channel) {
                $rendered = $this->renderTemplate(
                    $setting->message_template,
                    $doctor->name,
                    $targetDate,
                    $doctorAppointments,
                    $clinic?->name ?? 'Clinic'
                );

                $logs[] = DoctorReminderLog::query()->create([
                    'clinic_id' => $clinicId,
                    'doctor_user_id' => $doctor->id,
                    'channel' => $channel,
                    'message_template' => $setting->message_template,
                    'rendered_message' => $rendered,
                    'reminder_date' => $targetDate->toDateString(),
                    'status' => 'sent',
                    'triggered_at' => now(),
                    'payload' => [
                        'appointments_count' => $doctorAppointments->count(),
                        'appointment_ids' => $doctorAppointments->pluck('id')->values()->all(),
                    ],
                    'created_by' => $createdBy,
                ]);
            }
        }

        return ServiceResult::success([
            'triggered_count' => count($logs),
            'logs' => DoctorReminderLogResource::collection(collect($logs))->resolve(),
        ], 'Doctor reminders triggered successfully');
    }

    public function triggerForAllEnabledClinics(): void
    {
        DoctorReminderSetting::query()
            ->where('is_enabled', true)
            ->get()
            ->each(fn (DoctorReminderSetting $setting) => $this->triggerForClinic($setting->clinic_id));
    }

    public function triggerScheduledForAllEnabledClinics(): void
    {
        $now = now()->format('H:i');
        $targetDate = now()->addDay()->toDateString();

        DoctorReminderSetting::query()
            ->where('is_enabled', true)
            ->get()
            ->filter(fn (DoctorReminderSetting $setting) => substr((string) $setting->send_time, 0, 5) === $now)
            ->each(function (DoctorReminderSetting $setting) use ($targetDate): void {
                $alreadyTriggered = DoctorReminderLog::query()
                    ->where('clinic_id', $setting->clinic_id)
                    ->whereDate('reminder_date', $targetDate)
                    ->exists();

                if (! $alreadyTriggered) {
                    $this->triggerForClinic($setting->clinic_id, $targetDate);
                }
            });
    }

    public function logs(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $logs = DoctorReminderLog::query()
            ->with('doctor:id,name')
            ->where('clinic_id', $clinicId)
            ->latest('triggered_at')
            ->limit(100)
            ->get();

        return ServiceResult::success(
            DoctorReminderLogResource::collection($logs)->resolve(),
            'Doctor reminder logs fetched successfully'
        );
    }

    private function settingForClinic(int $clinicId): DoctorReminderSetting
    {
        return DoctorReminderSetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'is_enabled' => false,
                'send_time' => '20:00',
                'channels' => ['sms', 'whatsapp'],
                'message_template' => "Hello Dr. {DoctorName},\nHere is your schedule for tomorrow ({Date}):\n{AppointmentList}\n\n- {ClinicName}",
            ]
        );
    }

    private function renderTemplate(string $template, string $doctorName, Carbon $date, $appointments, string $clinicName): string
    {
        $appointmentList = $appointments
            ->map(fn (ClinicAppointment $appointment) => '- ' . $appointment->appointment_at?->format('H:i') . ' ' . ($appointment->patient?->user?->name ?? $appointment->patient_name) . ' (' . $appointment->service_name . ')')
            ->implode("\n");

        return str_replace(
            ['{DoctorName}', '{Date}', '{AppointmentList}', '{ClinicName}'],
            [$doctorName, $date->format('Y-m-d'), $appointmentList, $clinicName],
            $template
        );
    }
}
