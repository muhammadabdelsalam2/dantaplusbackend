<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Notifications\IndexNotificationLogsRequest;
use App\Services\Owner\NotificationLogService;
use App\Support\ApiResponse;

class NotificationLogController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationLogService $service) {}

    public function index(IndexNotificationLogsRequest $request)
    {
        $result = $this->service->list($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
