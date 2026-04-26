<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\ReminderLogResource;
use App\Http\Resources\Clinic\Settings\ReminderSettingsResource;
use App\Models\ClinicAppointment;
use App\Models\ReminderLog;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;

class ClinicReminderSettingsService
{
    public function __construct(private ClinicSettingsRepositoryInterface $repository)
    {
    }

    public function show(): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new ReminderSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Reminder settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        if (array_key_exists('timing', $data) && ! array_key_exists('times', $data)) {
            $data['times'] = $data['timing'];
        }

        foreach ($data as $key => $value) {
            $this->repository->upsertSetting($clinicId, 'reminders', $key, $value);
        }

        return ServiceResult::success(
            (new ReminderSettingsResource($this->mapSettings($clinicId)))->resolve(),
            'Reminder settings updated successfully'
        );
    }

    public function trigger(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $settings = (new ReminderSettingsResource($this->mapSettings($clinicId)))->resolve();
        $channel = $data['channel'] ?? $settings['channel'];
        $template = $data['template'] ?? $settings['template'];
        $limit = $data['limit'] ?? 25;

        $appointments = ClinicAppointment::query()
            ->with(['patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->where('appointment_at', '>=', now()->startOfDay())
            ->when(! empty($data['appointment_ids']), fn ($query) => $query->whereIn('id', $data['appointment_ids']))
            ->when(! empty($data['patient_ids']), fn ($query) => $query->whereIn('patient_id', $data['patient_ids']))
            ->orderBy('appointment_at')
            ->limit($limit)
            ->get();

        $logs = [];

        foreach ($appointments as $appointment) {
            // Trigger is intentionally simulated for production-safe rollout: we persist a log and do not dispatch cron/jobs yet.
            $logs[] = ReminderLog::query()->create([
                'clinic_id' => $clinicId,
                'patient_id' => $appointment->patient_id,
                'clinic_appointment_id' => $appointment->id,
                'channel' => $channel,
                'template' => $this->renderTemplate($template, $appointment),
                'status' => 'simulated',
                'triggered_at' => now(),
                'payload' => [
                    'appointment_at' => optional($appointment->appointment_at)?->toISOString(),
                    'patient_name' => $appointment->patient?->user?->name ?? $appointment->patient_name,
                    'patient_phone' => $appointment->patient_phone,
                ],
                'created_by' => auth()->id(),
            ]);
        }

        $logIds = collect($logs)->pluck('id')->all();

        return ServiceResult::success([
            'triggered_count' => count($logs),
            'logs' => ReminderLogResource::collection(
                ReminderLog::query()
                    ->with(['patient.user:id,name', 'appointment'])
                    ->whereIn('id', $logIds)
                    ->latest('triggered_at')
                    ->get()
            )->resolve(),
        ], 'Reminder trigger simulated successfully');
    }

    public function logs(): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $logs = ReminderLog::query()
            ->with(['patient.user:id,name', 'appointment'])
            ->where('clinic_id', $clinicId)
            ->latest('triggered_at')
            ->limit(100)
            ->get();

        return ServiceResult::success(
            ReminderLogResource::collection($logs)->resolve(),
            'Reminder logs fetched successfully'
        );
    }

    private function mapSettings(int $clinicId): array
    {
        return $this->repository->getSettingsGroup($clinicId, 'reminders')
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->all();
    }

    private function renderTemplate(string $template, ClinicAppointment $appointment): string
    {
        return str_replace(
            [':date', ':time', ':patient_name'],
            [
                optional($appointment->appointment_at)?->format('Y-m-d'),
                optional($appointment->appointment_at)?->format('H:i'),
                $appointment->patient?->user?->name ?? $appointment->patient_name,
            ],
            $template
        );
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
