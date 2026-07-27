<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateGlobalSettingsRequest;
use App\Support\ApiResponse;
use App\Services\SuperAdmin\SettingsService;

class GlobalSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function show()
    {
        return ApiResponse::success($this->settingsService->generalSettings());
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
