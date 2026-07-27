<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateBackupSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class BackupSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->backupSettings());
    }

    public function update(UpdateBackupSettingsRequest $request)
    {
        $this->settingsService->updateGroup('backup', $request->validated());

        return ApiResponse::success($this->settingsService->backupSettings(), 'Backup settings updated');
    }

    public function manual()
    {
        // Placeholder: In production, you can dispatch a job
        return ApiResponse::success([
            'status' => 'queued',
            'requested_at' => now()->toISOString(),
        ], 'Manual backup requested');
    }
}
