<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\UpdateWhatsAppRequest;
use App\Services\Lab\Settings\WhatsAppSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WhatsAppSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private WhatsAppSettingsService $whatsAppSettingsService)
    {
    }

    public function show()
    {
        $result = $this->whatsAppSettingsService->show();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateWhatsAppRequest $request)
    {
        $result = $this->whatsAppSettingsService->update($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function test()
    {
        $result = $this->whatsAppSettingsService->testConnection();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function logs()
    {
        $result = $this->whatsAppSettingsService->logs();

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            $result = $this->whatsAppSettingsService->verifyWebhook($request->query());

            if (!$result['success']) {
                return response($result['message'], $result['code']);
            }

            return response((string) ($result['data']['challenge'] ?? ''), 200);
        }

        $this->whatsAppSettingsService->handleWebhook($request->input());

        return ApiResponse::success(null, 'Webhook received');
    }
}
