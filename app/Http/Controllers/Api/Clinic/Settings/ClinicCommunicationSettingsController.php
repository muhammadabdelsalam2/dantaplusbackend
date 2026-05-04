<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\TestClinicCommunicationConnectionRequest;
use App\Http\Requests\Clinic\Settings\UpdateClinicCommunicationSmsEmailRequest;
use App\Http\Requests\Clinic\Settings\UpdateClinicCommunicationTemplateRequest;
use App\Http\Requests\Clinic\Settings\UpdateClinicCommunicationWhatsAppRequest;
use App\Services\Clinic\Settings\ClinicCommunicationSettingsService;
use App\Support\ApiResponse;

class ClinicCommunicationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicCommunicationSettingsService $service)
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

    public function updateWhatsApp(UpdateClinicCommunicationWhatsAppRequest $request)
    {
        $result = $this->service->updateWhatsApp($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateSmsEmail(UpdateClinicCommunicationSmsEmailRequest $request)
    {
        $result = $this->service->updateSmsEmail($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function testConnection(TestClinicCommunicationConnectionRequest $request)
    {
        $result = $this->service->testConnection($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function webhookUrl()
    {
        $result = $this->service->webhookUrl();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateTemplate(UpdateClinicCommunicationTemplateRequest $request, int $id)
    {
        $result = $this->service->updateTemplate($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
