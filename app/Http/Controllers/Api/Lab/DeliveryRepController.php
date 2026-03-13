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

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->deliveryRepService->show($id);

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateDeliveryRepRequest $request, int $id)
    {
        $payload = $request->validated();

        if ($request->hasFile('profile_photo')) {
            $payload['profile_photo'] = $request->file('profile_photo');
        }

        $result = $this->deliveryRepService->update($id, $payload);

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->deliveryRepService->destroy($id);

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
