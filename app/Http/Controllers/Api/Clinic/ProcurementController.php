<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexProcurementOrderRequest;
use App\Http\Resources\Clinic\ProcurementOrderResource;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\ProcurementOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    use ApiResponse;

    public function index(IndexProcurementOrderRequest $request)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validated();
        $orders = ProcurementOrder::query()
            ->with(['material:id,name', 'supplier:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('material', fn ($materialQuery) => $materialQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 15));

        $statsQuery = ProcurementOrder::query()->where('clinic_id', $clinicId);

        return ApiResponse::success([
            'stats' => [
                'awaiting_approval' => (clone $statsQuery)->where('status', ProcurementOrder::STATUS_PENDING)->count(),
                'total_commitment' => round((float) (clone $statsQuery)
                    ->whereIn('status', [ProcurementOrder::STATUS_PENDING, ProcurementOrder::STATUS_ORDERED])
                    ->sum('total_cost'), 2),
                'successful_restocks' => (clone $statsQuery)->where('status', ProcurementOrder::STATUS_RECEIVED)->count(),
            ],
            'orders' => ProcurementOrderResource::collection($orders->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 'Procurement orders fetched successfully');
    }

    public function approve(int $po)
    {
        $order = $this->findOrder($po);
        if (! $order) {
            return ApiResponse::error('Procurement order not found.', 404);
        }

        if ($order->status !== ProcurementOrder::STATUS_PENDING) {
            return ApiResponse::error('Only pending procurement orders can be approved.', 422);
        }

        $order->update([
            'status' => ProcurementOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        return ApiResponse::success((new ProcurementOrderResource($order->fresh(['material:id,name', 'supplier:id,name'])))->resolve(), 'Procurement order approved successfully');
    }

    public function receive(int $po)
    {
        $order = $this->findOrder($po);
        if (! $order) {
            return ApiResponse::error('Procurement order not found.', 404);
        }

        if (! in_array($order->status, [ProcurementOrder::STATUS_PENDING, ProcurementOrder::STATUS_ORDERED], true)) {
            return ApiResponse::error('Only pending or ordered procurement orders can be received.', 422);
        }

        DB::transaction(function () use ($order) {
            $inventory = InventoryItem::query()
                ->withoutGlobalScopes()
                ->where('clinic_id', $order->clinic_id)
                ->where('product_id', $order->material_id)
                ->first();

            if (! $inventory) {
                $material = $order->material()->with('company')->firstOrFail();

                $inventory = InventoryItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $material->company_id,
                    'clinic_id' => $order->clinic_id,
                    'product_id' => $material->id,
                    'barcode' => $material->barcode,
                    'product_name' => $material->name,
                    'category_name' => $material->category,
                    'description' => $material->description,
                    'quantity' => 0,
                    'minimum_stock_level' => 0,
                    'reorder_quantity' => 0,
                    'unit' => 'piece',
                    'supplier' => $order->supplier?->name ?? $order->supplier_name,
                    'status' => 'in_stock',
                    'last_updated_at' => now(),
                ]);
            }

            $newQuantity = (int) $inventory->quantity + (int) $order->qty;
            $minimumStockLevel = (int) $inventory->minimum_stock_level;

            $inventory->update([
                'quantity' => $newQuantity,
                'status' => $newQuantity <= 0 ? 'out_of_stock' : ($newQuantity <= $minimumStockLevel ? 'low_stock' : 'in_stock'),
                'last_updated_at' => now(),
            ]);

            InventoryLog::query()->withoutGlobalScopes()->create([
                'inventory_item_id' => $inventory->id,
                'company_id' => $inventory->company_id,
                'clinic_id' => $inventory->clinic_id,
                'user_id' => auth()->id(),
                'action' => 'restock_received',
                'amount' => (int) $order->qty,
                'reason' => 'Procurement order received: ' . $order->po_number,
                'created_at' => now(),
            ]);

            $order->update([
                'status' => ProcurementOrder::STATUS_RECEIVED,
                'ordered_at' => $order->ordered_at ?? now(),
                'received_at' => now(),
            ]);
        });

        return ApiResponse::success((new ProcurementOrderResource($order->fresh(['material:id,name', 'supplier:id,name'])))->resolve(), 'Procurement order received successfully');
    }

    public function cancel(int $po)
    {
        $order = $this->findOrder($po);
        if (! $order) {
            return ApiResponse::error('Procurement order not found.', 404);
        }

        if ($order->status === ProcurementOrder::STATUS_RECEIVED) {
            return ApiResponse::error('Received procurement orders cannot be cancelled.', 422);
        }

        $order->update([
            'status' => ProcurementOrder::STATUS_CANCELLED,
        ]);

        return ApiResponse::success((new ProcurementOrderResource($order->fresh(['material:id,name', 'supplier:id,name'])))->resolve(), 'Procurement order cancelled successfully');
    }

    private function findOrder(int $id): ?ProcurementOrder
    {
        return ProcurementOrder::query()
            ->with(['material:id,name', 'supplier:id,name'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->find($id);
    }
}
