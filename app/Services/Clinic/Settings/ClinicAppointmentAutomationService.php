<?php

namespace App\Services\Clinic\Settings;

use App\Models\ClinicAppointment;

class ClinicAppointmentAutomationService
{
    public function __construct(
        private ClinicQueueNotificationSettingsService $queueNotificationService,
        private ClinicFeedbackSettingsService $feedbackSettingsService,
    ) {
    }

    public function handleStatusChange(ClinicAppointment $appointment, ?string $fromStatus, ?string $toStatus): void
    {
        $normalizedTo = strtolower((string) $toStatus);
        $normalizedFrom = strtolower((string) $fromStatus);

        if ($normalizedTo === $normalizedFrom) {
            return;
        }

        if ($normalizedTo === 'arrived') {
            $this->queueNotificationService->notifyUpcomingPatients($appointment);
        }

        if ($normalizedTo === 'attended') {
            $this->feedbackSettingsService->scheduleFeedbackForAppointment($appointment);
        }
    }
}
