<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\IndexNotificationsRequest;
use App\Http\Requests\Notifications\MarkNotificationReadRequest;
use App\Http\Requests\Notifications\StoreNotificationRequest;
use App\Services\NotificationService;
use App\Support\ApiResponse;

class NotificationCenterController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationService $service) {}

    public function index(IndexNotificationsRequest $request)
    {
        $result = $this->service->listNotifications($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreNotificationRequest $request)
    {
        $result = $this->service->sendNotification($request->validated(), $request->user());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function test(StoreNotificationRequest $request)
    {
        $result = $this->service->sendNotification($request->validated(), $request->user(), true);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function markRead(MarkNotificationReadRequest $request, int $id)
    {
        $result = $this->service->markAsRead($id, $request->user(), false);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
