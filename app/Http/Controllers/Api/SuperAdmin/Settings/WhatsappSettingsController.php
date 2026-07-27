<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateWhatsappSettingsRequest;
use App\Http\Requests\SuperAdmin\Settings\UpsertWhatsappTemplateRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsappSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show(Request $request)
    {
        return ApiResponse::success($this->settingsService->whatsappSettings());
    }

    public function update(UpdateWhatsappSettingsRequest $request)
    {
        return ApiResponse::success(
            $this->settingsService->updateWhatsappRuntimeSettings($request->validated()),
            'WhatsApp settings updated'
        );
    }

    public function reconnect()
    {
        // Placeholder: in real integration, call provider API
        $data = $this->settingsService->updateWhatsappRuntimeSettings(['device_status' => 'Connected']);

        return ApiResponse::success($data, 'Reconnect requested');
    }

    public function testMessage(Request $request)
    {
        $request->validate([
            'to' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Placeholder: validate config exists
        $cfg = $this->settingsService->whatsappSettings();
        if (empty($cfg['base_url']) || empty($cfg['api_key']) || empty($cfg['device_id'])) {
            return ApiResponse::error('WhatsApp integration is not configured.', 422);
        }

        return ApiResponse::success(['sent' => true], 'Test message sent');
    }

    public function listTemplates()
    {
        return ApiResponse::success($this->settingsService->whatsappTemplates());
    }

    public function upsertTemplate(string $templateKey, UpsertWhatsappTemplateRequest $request)
    {
        // templateKey comes from URL to support fixed keys or dynamic keys
        if (strlen($templateKey) > 100) {
            throw ValidationException::withMessages(['templateKey' => 'Template key is too long.']);
        }

        return ApiResponse::success(
            $this->settingsService->saveWhatsappTemplate($templateKey, $request->validated()),
            'Template saved'
        );
    }
}
