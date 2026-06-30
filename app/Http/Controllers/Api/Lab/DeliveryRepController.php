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
}
