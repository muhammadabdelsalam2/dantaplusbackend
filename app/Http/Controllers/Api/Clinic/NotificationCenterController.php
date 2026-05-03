<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\IndexNotificationsRequest;
use App\Http\Requests\Notifications\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notifications\MarkNotificationReadRequest;
use App\Services\NotificationService;
use App\Support\ApiResponse;

class NotificationCenterController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationService $service) {}

    public function index(IndexNotificationsRequest $request)
    {
        $result = $this->service->getUserNotifications($request->user(), $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function unread(IndexNotificationsRequest $request)
    {
        $result = $this->service->getUserNotifications($request->user(), $request->validated(), true);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function markRead(MarkNotificationReadRequest $request, int $id)
    {
        $result = $this->service->markAsRead($id, $request->user());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function markAllRead(MarkAllNotificationsReadRequest $request)
    {
        $result = $this->service->markAllAsRead($request->user(), $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
