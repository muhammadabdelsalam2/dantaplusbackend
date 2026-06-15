<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicOrderRequest;
use App\Http\Requests\Clinic\PayClinicOrderRequest;
use App\Http\Resources\Clinic\ClinicOrderResource;
use App\Http\Resources\Clinic\ProcurementOrderResource;
use App\Models\Order;
use App\Models\ProcurementOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

  public function index(IndexClinicOrderRequest $request)
{
    $clinicId = auth()->user()?->clinic_id;
    if (! $clinicId) {
        return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
    }

    $validated = $request->validated();

    $orders = Order::query()
        ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
        ->with(['supplierCompany:id,name', 'items.product:id,name'])
        ->where('clinic_id', $clinicId)
        ->when($validated['search'] ?? null, fn ($query, $search) =>
            $query->where('order_code', 'like', "%{$search}%")
        )
        ->when($validated['status'] ?? null, fn ($query, $status) =>
            $query->where('status', $status)
        )
        ->when($validated['payment_method'] ?? null, fn ($query, $paymentMethod) =>
            $query->where('payment_method', $paymentMethod)
        )
        ->when($validated['payment_status'] ?? null, fn ($query, $paymentStatus) =>
            $query->where('payment_status', $paymentStatus)
        )
        ->when($validated['date_from'] ?? null, fn ($query, $date) =>
            $query->whereDate('order_date', '>=', $date)
        )
        ->when($validated['date_to'] ?? null, fn ($query, $date) =>
            $query->whereDate('order_date', '<=', $date)
        )
        ->when($validated['min_price'] ?? null, fn ($query, $price) =>
            $query->whereRaw('COALESCE(total_amount, amount_total) >= ?', [$price])
        )
        ->when($validated['max_price'] ?? null, fn ($query, $price) =>
            $query->whereRaw('COALESCE(total_amount, amount_total) <= ?', [$price])
        )
        ->latest('order_date')
        ->paginate((int) ($validated['per_page'] ?? 15));

    return ApiResponse::success([
        'orders' => ClinicOrderResource::collection($orders->getCollection())->resolve(),
        'pagination' => [
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'per_page'     => $orders->perPage(),
            'total'        => $orders->total(),
        ],
    ], 'Orders fetched successfully');
}


    public function show(int $order)
    {
        $order = $this->findOrder($order);
        if (! $order) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success((new ClinicOrderResource($order))->resolve(), 'Order fetched successfully');
    }

    public function approveChanges(int $order)
    {
        $order = $this->findOrder($order);
        if (! $order) {
            return ApiResponse::error('Order not found.', 404);
        }

        DB::transaction(function () use ($order) {
            $total = 0;

            $order->items->each(function ($item) use (&$total) {
                $approvedQty = $item->qty_modified !== null ? (int) $item->qty_modified : (int) $item->quantity;
                $lineTotal = round($approvedQty * (float) $item->unit_price, 2);

                $item->update([
                    'quantity' => $approvedQty,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            });

            $order->update([
                'amount_total' => $total,
                'total_amount' => $total,
                'status' => 'processing',
                'modified_by_supplier' => false,
            ]);
        });

        return ApiResponse::success((new ClinicOrderResource($order->fresh(['supplierCompany:id,name', 'items.product:id,name'])))->resolve(), 'Order changes approved successfully');
    }

    public function rejectChanges(int $order)
    {
        $order = $this->findOrder($order);
        if (! $order) {
            return ApiResponse::error('Order not found.', 404);
        }

        $order->update([
            'status' => 'cancelled',
            'modified_by_supplier' => false,
        ]);

        return ApiResponse::success((new ClinicOrderResource($order->fresh(['supplierCompany:id,name', 'items.product:id,name'])))->resolve(), 'Order changes rejected successfully');
    }

    public function pay(PayClinicOrderRequest $request, int $order)
    {
        $order = $this->findOrder($order);
        if (! $order) {
            return ApiResponse::error('Order not found.', 404);
        }

        $validated = $request->validated();

        $order->update([
            'payment_method' => $validated['payment_method'] ?? $order->payment_method,
            'payment_status' => $validated['payment_status'] ?? 'paid',
            'payment_reference' => $validated['payment_reference'] ?? $order->payment_reference,
        ]);

        return ApiResponse::success((new ClinicOrderResource($order->fresh(['supplierCompany:id,name', 'items.product:id,name'])))->resolve(), 'Order payment updated successfully');
    }

    public function restock(int $order)
    {
        $order = $this->findOrder($order);
        if (! $order) {
            return ApiResponse::error('Order not found.', 404);
        }

        $createdOrders = DB::transaction(function () use ($order) {
            return $order->items->map(function ($item) use ($order) {
                $procurementOrder = ProcurementOrder::create([
                    'clinic_id' => $order->clinic_id,
                    'material_id' => $item->product_id,
                    'supplier_id' => $order->supplier_company_id,
                    'supplier_name' => $order->supplierCompany?->name ?? 'Unknown Supplier',
                    'qty' => (int) ($item->qty_modified ?? $item->quantity),
                    'unit_cost' => $item->unit_price,
                    'total_cost' => round((int) ($item->qty_modified ?? $item->quantity) * (float) $item->unit_price, 2),
                    'status' => ProcurementOrder::STATUS_ORDERED,
                    'po_number' => 'PO-' . now()->timestamp . '-draft-' . uniqid(),
                    'notes' => 'Restocked from order ' . $order->order_code,
                    'ordered_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $procurementOrder->update([
                    'po_number' => 'PO-' . now()->timestamp . '-' . $procurementOrder->id,
                ]);

                return $procurementOrder->load(['material:id,name', 'supplier:id,name']);
            });
        });

        return ApiResponse::success([
            'orders' => ProcurementOrderResource::collection($createdOrders)->resolve(),
        ], 'Procurement orders created from order successfully', 201);
    }

    private function findOrder(int $id): ?Order
    {
        return Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->with(['supplierCompany:id,name', 'items.product:id,name'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->find($id);
    }
}
