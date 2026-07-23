<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\DeliveryRep\IndexDeliveryRepRequest;
use App\Http\Requests\Lab\DeliveryRep\StoreDeliveryRepRequest;
use App\Http\Requests\Lab\DeliveryRep\UpdateDeliveryRepRequest;
use App\Services\Lab\DeliveryRepService;
use App\Support\ApiResponse;

class DeliveryRepController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DeliveryRepService $deliveryRepService
    ) {
    }

    public function index(IndexDeliveryRepRequest $request)
    {
        $result = $this->deliveryRepService->index($request->validated());

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreDeliveryRepRequest $request)
    {
        $payload = $request->validated();

        if ($request->hasFile('profile_photo')) {
            $payload['profile_photo'] = $request->file('profile_photo');
        }

        $result = $this->deliveryRepService->store($payload);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->deliveryRepService->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function update(UpdateDeliveryRepRequest $request, int $id)
    {
        $payload = $request->validated();

        if ($request->hasFile('profile_photo')) {
            $payload['profile_photo'] = $request->file('profile_photo');
        }

        $result = $this->deliveryRepService->update($id, $payload);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->deliveryRepService->destroy($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function tasks(\Illuminate\Http\Request $request, int $id, \App\Services\Lab\DeliveryTrackingService $deliveryTrackingService)
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id) {
            return ApiResponse::error('Authenticated lab account is required.', 403);
        }

        $rep = \App\Models\LabDeliveryRep::query()
            ->where('lab_id', $authUser->lab_id)
            ->with('user')
            ->findOrFail($id);

        if (! $rep->user || ! $rep->user->hasRole('delivery_representative')) {
            return ApiResponse::error('Delivery representative is invalid or lacks the required role.', 422);
        }

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:assigned,picked_up,in_transit,delivered,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filters['delivery_rep_user_id'] = $rep->user_id;

        $tasks = $deliveryTrackingService->paginateForUser($authUser, $filters);

        return ApiResponse::success([
            'items' => collect($tasks->items())->map(fn (\App\Models\DeliveryTask $task) => $deliveryTrackingService->mapTask($task))->all(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], 'Delivery tasks fetched successfully');
    }

    public function loginAs(\Illuminate\Http\Request $request, int $id)
    {
        $result = $this->deliveryRepService->loginAs($id, $request->ip(), $request->userAgent());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function myDeliveries(\Illuminate\Http\Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:assigned,picked_up,in_transit,delivered,cancelled'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->deliveryRepService->myDeliveries($request->user(), $filters);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function myReports(\Illuminate\Http\Request $request)
    {
        $filters = $request->validate([
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $result = $this->deliveryRepService->myReports($request->user(), $filters);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function myDeliveryDetails(\Illuminate\Http\Request $request, int $taskId)
    {
        $result = $this->deliveryRepService->deliveryTaskDetails($taskId, $request->user());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function confirmPickup(\Illuminate\Http\Request $request, int $taskId)
    {
        $payload = $request->validate([
            'photo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'trip_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expenses' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);
        $payload['photo'] = $request->file('photo');

        $result = $this->deliveryRepService->confirmPickup($taskId, $payload, $request->user());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function updateLiveLocation(\Illuminate\Http\Request $request)
    {
        $payload = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $result = $this->deliveryRepService->updateLiveLocation($payload, $request->user());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }

    public function liveTracking(\Illuminate\Http\Request $request)
    {
        $result = $this->deliveryRepService->liveTracking($request->all());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message'], $result['code']);
    }
}
