<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicDentalLabsRequest;
use App\Http\Requests\Clinic\IndexClinicDentalLabOrdersRequest;
use App\Http\Requests\Clinic\RateDentalLabRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabGalleryRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabOrderRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabServiceRequest;
use App\Http\Requests\Clinic\StoreClinicDentalLabRequest;
use App\Http\Requests\Clinic\StoreLabOrderForLabRequest;
use App\Http\Requests\Clinic\UpdateClinicDentalLabRequest;
use App\Http\Requests\Clinic\UpdateClinicDentalLabOrderStatusRequest;
use App\Http\Resources\Clinic\ClinicDentalLabOrderDetailResource;
use App\Services\Clinic\ClinicDentalLabService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

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

    public function storeOrderForLab(StoreLabOrderForLabRequest $request, int $id)
    {
        $result = $this->service->storeOrderForLab($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function rate(RateDentalLabRequest $request, int $id)
    {
        $result = $this->service->rate($id, $request->validated());

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

    public function prototypeIndex()
    {
        $result = $this->service->prototypeIndex();

        return $result['success']
            ? response()->json(['data' => $result['data']], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function prototypeShow(int $id)
    {
        $result = $this->service->prototypeShow($id);

        return $result['success']
            ? response()->json($result['data'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function prototypeStoreOrder(Request $request)
    {
        $validated = $request->validate([
            'lab_id' => ['required', 'integer', 'exists:dental_labs,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'dentist_id' => ['required', 'integer', 'exists:users,id'],
            'case_type_id' => ['required', 'integer', 'min:1'],
            'tooth_numbers' => ['required', 'array', 'min:1'],
            'tooth_numbers.*' => ['integer', 'min:1', 'max:32'],
            'description' => ['nullable', 'string'],
            'material_id' => ['required', 'integer', 'min:1'],
            'shade_id' => ['required', 'integer', 'min:1'],
            'delivery_date' => ['required', 'date'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $result = $this->service->prototypeStoreOrder($validated);

        return $result['success']
            ? response()->json($result['data'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
