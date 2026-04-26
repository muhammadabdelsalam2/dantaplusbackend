<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\TriggerDoctorReminderRequest;
use App\Http\Requests\Clinic\Settings\UpdateDoctorReminderSettingsRequest;
use App\Services\Clinic\Settings\ClinicDoctorReminderSettingsService;
use App\Support\ApiResponse;

class ClinicDoctorReminderSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicDoctorReminderSettingsService $service)
    {
    }

    public function show()
    {
        $result = $this->service->show();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateDoctorReminderSettingsRequest $request)
    {
        $result = $this->service->update($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function trigger(TriggerDoctorReminderRequest $request)
    {
        $result = $this->service->triggerForClinic(
            auth()->user()?->clinic_id,
            $request->validated('date'),
            auth()->id(),
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function logs()
    {
        $result = $this->service->logs();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
