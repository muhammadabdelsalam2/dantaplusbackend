<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateOrderRequest;
use App\Http\Requests\Company\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Company\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $service) {}

    public function index(Request $request) { return ApiResponse::success($this->service->paginate($request->all()), 'Orders fetched successfully'); }
    public function show(Order $id) { return ApiResponse::success($this->service->show($id), 'Order fetched successfully'); }
    public function updateStatus(UpdateOrderStatusRequest $request, Order $id) { return ApiResponse::success($this->service->updateStatus($id, $request->validated()['status']), 'Order status updated successfully'); }
    public function update(UpdateOrderRequest $request, Order $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'Order updated successfully'); }
    public function complete(Order $id) { return ApiResponse::success($this->service->complete($id), 'Order completed successfully'); }
    public function communicationLogs(Order $id) { return ApiResponse::success($this->service->communicationLogs($id), 'Communication logs fetched successfully'); }
    public function clinics() { return ApiResponse::success($this->service->clinicsFilterOptions(), 'Clinics fetched successfully'); }
}
