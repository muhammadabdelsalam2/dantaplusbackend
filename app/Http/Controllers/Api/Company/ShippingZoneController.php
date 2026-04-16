<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreShippingZoneRequest;
use App\Http\Requests\Company\UpdateShippingZoneRequest;
use App\Models\ShippingZone;
use App\Services\Company\ShippingZoneService;
use App\Support\ApiResponse;

class ShippingZoneController extends Controller
{
    use ApiResponse;

    public function __construct(private ShippingZoneService $service) {}

    public function index() { return ApiResponse::success($this->service->index(), 'Shipping zones fetched successfully'); }
    public function store(StoreShippingZoneRequest $request) { return ApiResponse::success($this->service->create($request->validated()), 'Shipping zone created successfully', 201); }
    public function update(UpdateShippingZoneRequest $request, ShippingZone $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'Shipping zone updated successfully'); }
    public function destroy(ShippingZone $id) { $this->service->delete($id); return ApiResponse::success(null, 'Shipping zone deleted successfully'); }
    public function toggleStatus(ShippingZone $id) { return ApiResponse::success($this->service->toggle($id), 'Shipping zone status updated successfully'); }
}
