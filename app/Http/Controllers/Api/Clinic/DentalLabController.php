<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicDentalLabsRequest;
use App\Http\Requests\Clinic\IndexClinicDentalLabOrdersRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabGalleryRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabOrderRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabServiceRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabRequest;
use App\Http\Requests\Clinic\UpdateClinicDentalLabRequest;
use App\Http\Requests\Clinic\UpdateClinicDentalLabOrderStatusRequest;
use App\Http\Resources\Clinic\ClinicDentalLabOrderDetailResource;
use App\Services\Clinic\ClinicDentalLabService;
use App\Support\ApiResponse;

class DentalLabController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicDentalLabService $service)
    {
    }

    public function index(IndexClinicDentalLabsRequest $request)
    {
        $result = $this->service->index($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function store(StoreClinicDentalLabRequest $request)
    {
        $result = $this->service->store($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function show(int $id)
    {
        $result = $this->service->show($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function update(UpdateClinicDentalLabRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function destroy(int $id)
    {
        $result = $this->service->destroy($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function storeService(StoreClinicDentalLabServiceRequest $request, int $id)
    {
        $result = $this->service->storeService($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function destroyService(int $id)
    {
        $result = $this->service->deleteService($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function orders(IndexClinicDentalLabOrdersRequest $request)
    {
        $result = $this->service->indexOrders($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function storeOrder(StoreClinicDentalLabOrderRequest $request)
    {
        $result = $this->service->storeOrder($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateOrderStatus(UpdateClinicDentalLabOrderStatusRequest $request, int $id)
    {
        $result = $this->service->updateOrderStatus($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function storeGallery(StoreClinicDentalLabGalleryRequest $request, int $id)
    {
        $result = $this->service->uploadGallery($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function analytics()
    {
        $result = $this->service->analytics();

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function showOrder(int $id)
    {
        $result = $this->service->showOrder($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
