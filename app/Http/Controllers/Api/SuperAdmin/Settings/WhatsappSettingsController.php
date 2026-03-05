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

    private const GROUP = 'whatsapp';
    private const GROUP_TEMPLATES = 'whatsapp_templates';

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show(Request $request)
    {
        $data = $this->settingsService->getGroup(self::GROUP);

        // webhook is read-only computed (example)
        $data['webhook_url'] = url('/api/webhooks/whatsapp');

        // device status: unknown unless integration implemented
        $data['device_status'] = $data['device_status'] ?? 'unknown';

        // Mask api_key in response (optional security)
        if (!empty($data['api_key']) && is_string($data['api_key'])) {
            $data['api_key_masked'] = substr($data['api_key'], 0, 4) . '****' . substr($data['api_key'], -4);
            unset($data['api_key']);
        }

        return ApiResponse::success($data);
    }

    public function update(UpdateWhatsappSettingsRequest $request)
    {
        $values = $request->validated();

        $data = $this->settingsService->updateGroup(self::GROUP, $values, encryptedKeys: ['api_key']);

        // mask
        if (!empty($data['api_key']) && is_string($data['api_key'])) {
            $data['api_key_masked'] = substr($data['api_key'], 0, 4) . '****' . substr($data['api_key'], -4);
            unset($data['api_key']);
        }

        return ApiResponse::success($data, 'WhatsApp settings updated');
    }

    public function reconnect()
    {
        // Placeholder: in real integration, call provider API
        return ApiResponse::success(['status' => 'queued'], 'Reconnect requested');
    }

    public function testMessage(Request $request)
    {
        $request->validate([
            'to' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Placeholder: validate config exists
        $cfg = $this->settingsService->getGroup(self::GROUP);
        if (empty($cfg['base_url']) || empty($cfg['api_key']) || empty($cfg['device_id'])) {
            return ApiResponse::error('WhatsApp integration is not configured.', 422);
        }

        return ApiResponse::success(['sent' => false], 'Test message accepted (integration stub)');
    }

    public function listTemplates()
    {
        $data = $this->settingsService->getGroup(self::GROUP_TEMPLATES);
        return ApiResponse::success($data);
    }

    public function upsertTemplate(string $templateKey, UpsertWhatsappTemplateRequest $request)
    {
        // templateKey comes from URL to support fixed keys or dynamic keys
        if (strlen($templateKey) > 100) {
            throw ValidationException::withMessages(['templateKey' => 'Template key is too long.']);
        }

        $data = $this->settingsService->updateGroup(self::GROUP_TEMPLATES, [
            $templateKey => $request->validated()['content']
        ]);

        return ApiResponse::success($data, 'Template saved');
    }
}
