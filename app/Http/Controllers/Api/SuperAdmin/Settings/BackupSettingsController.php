<?php

namespace App\Http\Controllers\Api\SuperAdmin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Settings\UpdateBackupSettingsRequest;
use App\Services\SuperAdmin\SettingsService;
use App\Support\ApiResponse;

class BackupSettingsController extends Controller
{
    use ApiResponse;

    private const GROUP = 'backup';

    public function __construct(private SettingsService $settingsService)
    {
    }

    public function show()
    {
        return ApiResponse::success($this->settingsService->getGroup(self::GROUP));
    }

    public function update(UpdateBackupSettingsRequest $request)
    {
        $data = $this->settingsService->updateGroup(self::GROUP, $request->validated());
        return ApiResponse::success($data, 'Backup settings updated');
    }

    public function manual()
    {
        // Placeholder: In production, you can dispatch a job
        return ApiResponse::success(['status' => 'queued'], 'Manual backup requested');
    }
}
