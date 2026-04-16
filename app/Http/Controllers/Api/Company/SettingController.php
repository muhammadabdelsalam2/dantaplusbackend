<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateSettingsRequest;
use App\Services\Company\SettingService;
use App\Support\ApiResponse;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingService $service) {}

    public function show() { return ApiResponse::success($this->service->getSettings(), 'Settings fetched successfully'); }
    public function updateProfile(UpdateSettingsRequest $request) { return ApiResponse::success($this->service->updateSection('profile', $request->validated('profile', [])), 'Profile settings updated successfully'); }
    public function updateCommunication(UpdateSettingsRequest $request) { return ApiResponse::success($this->service->updateSection('communication', $request->validated('communication', [])), 'Communication settings updated successfully'); }
    public function testCommunication() { return ApiResponse::success($this->service->testCommunication(), 'Communication test queued successfully'); }
    public function updateAutomation(UpdateSettingsRequest $request) { return ApiResponse::success($this->service->updateSection('automation', $request->validated('automation', [])), 'Automation settings updated successfully'); }
    public function whatsappLogs() { return ApiResponse::success($this->service->whatsappLogs(), 'WhatsApp logs fetched successfully'); }
}
