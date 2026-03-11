<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Notifications\IndexNotificationsRequest;
use App\Http\Requests\Owner\Notifications\MarkNotificationReadRequest;
use App\Http\Requests\Owner\Notifications\StoreNotificationRequest;
use App\Services\Owner\NotificationCenterService;
use App\Support\ApiResponse;

class NotificationCenterController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationCenterService $service) {}

    public function index(IndexNotificationsRequest $request)
    {
        $result = $this->service->list($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreNotificationRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function test(StoreNotificationRequest $request)
    {
        $result = $this->service->create($request->validated(), true);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function markRead(MarkNotificationReadRequest $request, int $id)
    {
        $result = $this->service->markRead($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
