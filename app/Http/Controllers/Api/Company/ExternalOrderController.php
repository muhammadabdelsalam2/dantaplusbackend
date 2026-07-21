<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreExternalOrderRequest;
use App\Http\Requests\Company\UpdateOrderRequest;
use App\Http\Requests\Company\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Company\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ExternalOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $service) {}

    public function store(StoreExternalOrderRequest $request) { return ApiResponse::success($this->service->createExternal($request->validated()), 'External order created successfully', 201); }
    public function index(Request $request) { return ApiResponse::success($this->service->paginate($request->all(), 'external'), 'External orders fetched successfully'); }
    public function update(UpdateOrderRequest $request, Order $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'External order updated successfully'); }
    public function updateStatus(UpdateOrderStatusRequest $request, Order $id) { return ApiResponse::success($this->service->updateStatus($id, $request->validated()['status']), 'External order status updated successfully'); }
    public function sendWhatsApp(Order $id) { return ApiResponse::success($this->service->sendExternalOrderWhatsApp($id), 'WhatsApp send attempted'); }
}
