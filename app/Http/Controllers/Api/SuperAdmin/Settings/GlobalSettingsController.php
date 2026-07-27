<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateGlobalSettingsRequest;
use App\Support\ApiResponse;
use App\Services\SuperAdmin\SettingsService;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlobalSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function show()
    {
        return ApiResponse::success($this->settingsService->generalSettings());
    }

    public function timezones()
    {
        return ApiResponse::success([
            'timezones' => $this->settingsService->timezones(),
        ]);
    }

    public function saveAll(Request $request)
    {
        $validated = $request->validate([
            'global' => ['sometimes', 'array'],
            'global.system_name' => ['sometimes', 'string', 'max:255'],
            'global.support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'global.support_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'global.timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'global.default_language' => ['sometimes', 'string', 'max:50'],
            'global.default_currency' => ['sometimes', 'string', 'max:10'],
            'global.system_logo' => ['sometimes', 'nullable', 'array'],
            'global.system_favicon' => ['sometimes', 'nullable', 'array'],

            'whatsapp' => ['sometimes', 'array'],
            'whatsapp.device_status' => ['sometimes', 'string', Rule::in(['Connected', 'Disconnected'])],
            'whatsapp.message_templates' => ['sometimes', 'array'],
            'whatsapp.message_templates.*.key' => ['required_with:whatsapp.message_templates', 'string', 'max:100'],
            'whatsapp.message_templates.*.name' => ['sometimes', 'string', 'max:100'],
            'whatsapp.message_templates.*.content' => ['required_with:whatsapp.message_templates', 'string', 'max:5000'],

            'billing_plans' => ['sometimes', 'array'],
            'billing_plans.basic' => ['sometimes', 'array'],
            'billing_plans.basic.plan_name' => ['sometimes', 'string', 'max:100'],
            'billing_plans.basic.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.basic.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.basic.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'billing_plans.standard' => ['sometimes', 'array'],
            'billing_plans.standard.plan_name' => ['sometimes', 'string', 'max:100'],
            'billing_plans.standard.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.standard.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.standard.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'billing_plans.premium' => ['sometimes', 'array'],
            'billing_plans.premium.plan_name' => ['sometimes', 'string', 'max:100'],
            'billing_plans.premium.monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.premium.yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'billing_plans.premium.description' => ['sometimes', 'nullable', 'string', 'max:500'],

            'user_management' => ['sometimes', 'array'],
            'user_management.allow_new_signups' => ['sometimes', 'boolean'],
            'user_management.allow_trial_accounts' => ['sometimes', 'boolean'],

            'notifications' => ['sometimes', 'array'],
            'notifications.enable_system_email_notifications' => ['sometimes', 'boolean'],
            'notifications.enable_sms_whatsapp_notifications' => ['sometimes', 'boolean'],
            'notifications.notification_sounds' => ['sometimes', 'boolean'],

            'backup' => ['sometimes', 'array'],
            'backup.auto_backup_frequency' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'off'])],

            'customization' => ['sometimes', 'array'],
            'customization.dashboard_theme' => ['sometimes', 'string', Rule::in(['auto', 'dark', 'light'])],
            'customization.accent_color' => ['sometimes', 'string', 'max:30'],
        ]);

        if (isset($validated['global'])) {
            $this->settingsService->updateGeneralSettings($validated['global']);
        }

        if (isset($validated['whatsapp'])) {
            $this->settingsService->updateWhatsappRuntimeSettings($validated['whatsapp']);

            foreach ($validated['whatsapp']['message_templates'] ?? [] as $template) {
                $this->settingsService->saveWhatsappTemplate($template['key'], $template);
            }
        }

        foreach (['billing_plans', 'user_management', 'notifications', 'backup', 'customization'] as $group) {
            if (isset($validated[$group])) {
                $this->settingsService->updateGroup($group, $validated[$group]);
            }
        }

        return ApiResponse::success([
            'global' => $this->settingsService->generalSettings(),
            'whatsapp' => $this->settingsService->whatsappSettings(),
            'billing_plans' => $this->settingsService->billingSettings(),
            'user_management' => $this->settingsService->userManagementSettings(),
            'notifications' => $this->settingsService->notificationSettings(),
            'backup' => $this->settingsService->backupSettings(),
            'customization' => $this->settingsService->customizationSettings(),
        ], 'Settings saved');
    }

    public function update(UpdateGlobalSettingsRequest $request)
    {
        $values = $request->validated();

        if ($request->hasFile('logo_file')) {
            $values['system_logo'] = $this->settingsService->uploadPublicFile($request->file('logo_file'), 'settings/logos');
        } elseif (!empty($values['logo_base64'])) {
            $values['system_logo'] = $this->settingsService->uploadFromBase64(
                $values['logo_base64'],
                'settings/logos',
                $values['logo_filename'] ?? 'logo.png'
            );
        }

        if ($request->hasFile('favicon_file')) {
            $values['system_favicon'] = $this->settingsService->uploadPublicFile($request->file('favicon_file'), 'settings/favicons');
        } elseif (!empty($values['favicon_base64'])) {
            $values['system_favicon'] = $this->settingsService->uploadFromBase64(
                $values['favicon_base64'],
                'settings/favicons',
                $values['favicon_filename'] ?? 'favicon.png'
            );
        }

        $this->settingsService->updateGeneralSettings($values);

        return ApiResponse::success(
            $this->settingsService->generalSettings(),
            'Global settings updated'
        );
    }
}
