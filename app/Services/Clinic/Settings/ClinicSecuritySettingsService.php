<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\SecuritySettingsResource;
use App\Models\SecuritySetting;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\Artisan;

class ClinicSecuritySettingsService
{
    public function show(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            (new SecuritySettingsResource($this->settingForClinic($clinicId)))->resolve(),
            'Security settings fetched successfully'
        );
    }

    public function update(array $data): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $setting = $this->settingForClinic($clinicId);
        $setting->update($data);

        return ServiceResult::success(
            (new SecuritySettingsResource($setting->fresh()))->resolve(),
            'Security settings updated successfully'
        );
    }

    public function runManualBackup(array $data = []): array
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        Artisan::call('clinic:security-backup', [
            '--clinic' => $clinicId,
            '--manual' => true,
        ]);

        return ServiceResult::success([
            'status' => 'queued',
            'clinic_id' => $clinicId,
            'triggered_at' => now()->toISOString(),
            'note' => 'Backup execution is currently simulated.',
        ], 'Manual backup requested successfully', 202);
    }

    public function processScheduledBackups(): void
    {
        SecuritySetting::query()
            ->where('backup_schedule', '!=', 'disabled')
            ->get()
            ->each(function (SecuritySetting $setting): void {
                if ($this->shouldRunForSchedule($setting->backup_schedule)) {
                    Artisan::call('clinic:security-backup', [
                        '--clinic' => $setting->clinic_id,
                    ]);
                }
            });
    }

    private function shouldRunForSchedule(string $schedule): bool
    {
        return match ($schedule) {
            'daily' => true,
            'weekly' => now()->isSunday(),
            'monthly' => now()->day === 1,
            default => false,
        };
    }

    private function settingForClinic(int $clinicId): SecuritySetting
    {
        return SecuritySetting::query()->firstOrCreate(
            ['clinic_id' => $clinicId],
            [
                'enable_2fa' => false,
                'backup_schedule' => 'daily',
                'retention_days' => 3650,
            ]
        );
    }
}
