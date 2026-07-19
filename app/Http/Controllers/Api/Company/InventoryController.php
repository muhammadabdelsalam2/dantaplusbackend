<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StockAdjustmentRequest;
use App\Http\Requests\Company\StoreInventoryRequest;
use App\Http\Requests\Company\UpdateInventoryRequest;
use App\Models\InventoryItem;
use App\Services\Company\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(private InventoryService $service) {}

    public function index(Request $request) { return ApiResponse::success($this->service->paginate($request->all()), 'Inventory fetched successfully'); }
    public function store(StoreInventoryRequest $request) { return ApiResponse::success($this->service->create($request->validated()), 'Inventory item created successfully', 201); }
    public function show(InventoryItem $id) { return ApiResponse::success($this->service->show($id), 'Inventory item fetched successfully'); }
    public function update(UpdateInventoryRequest $request, InventoryItem $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'Inventory item updated successfully'); }
    public function destroy(InventoryItem $id) { $this->service->delete($id); return ApiResponse::success(null, 'Inventory item deleted successfully'); }
    public function stockAdjustment(StockAdjustmentRequest $request, InventoryItem $id) { return ApiResponse::success($this->service->adjust($id, $request->validated()), 'Stock adjusted successfully'); }
    public function logs(InventoryItem $id) { return ApiResponse::success($this->service->logs($id), 'Inventory logs fetched successfully'); }
    public function summary() { return ApiResponse::success($this->service->summary(), 'Inventory summary fetched successfully'); }
}
