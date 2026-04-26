<?php

use App\Services\Clinic\Settings\ClinicDoctorReminderSettingsService;
use App\Services\Clinic\Settings\ClinicFeedbackSettingsService;
use App\Services\Clinic\Settings\ClinicSecuritySettingsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clinic:doctor-reminders:send', function (ClinicDoctorReminderSettingsService $service) {
    $service->triggerScheduledForAllEnabledClinics();

    $this->info('Doctor reminders processed successfully.');
})->purpose('Send daily doctor reminders for enabled clinics');

Artisan::command('clinic:feedback:process', function (ClinicFeedbackSettingsService $service) {
    $service->processDueFeedbackLogs();

    $this->info('Feedback logs processed successfully.');
})->purpose('Send due feedback notifications');

Artisan::command('clinic:security-backup', function (ClinicSecuritySettingsService $service) {
    $clinicId = (int) $this->option('clinic');
    $manual = (bool) $this->option('manual');

    logger()->info('Clinic security backup simulated.', [
        'clinic_id' => $clinicId,
        'manual' => $manual,
        'executed_at' => now()->toISOString(),
    ]);

    $this->info('Clinic security backup simulated successfully.');
})->purpose('Run a simulated clinic backup')
    ->addOption('clinic', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED)
    ->addOption('manual', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE);

Artisan::command('clinic:security-backups:process', function (ClinicSecuritySettingsService $service) {
    $service->processScheduledBackups();

    $this->info('Scheduled clinic backups processed successfully.');
})->purpose('Process scheduled clinic security backups');

Schedule::command('clinic:doctor-reminders:send')->everyMinute();
Schedule::command('clinic:feedback:process')->everyMinute();
Schedule::command('clinic:security-backups:process')->dailyAt('00:05');
