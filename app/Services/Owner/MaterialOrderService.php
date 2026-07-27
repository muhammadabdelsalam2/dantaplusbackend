<?php

namespace App\Services\Owner;

use App\Models\MaterialOrder;
use App\Repositories\MaterialOrderRepository;
use App\Support\ServiceResult;

class MaterialOrderService
{
    public function __construct(private MaterialOrderRepository $materialOrderRepository)
    {
    }

   public function index(array $filters): array
{
    $perPage = (int) ($filters['per_page'] ?? 15);
    $orders = $this->materialOrderRepository->paginate($filters, $perPage);

    $items = collect($orders->items())->map(function ($o) {
        return [
            'id' => $o->id,
            'order_number' => $o->order_code,
            'clinic' => $o->clinic?->name,
            'company' => $o->supplierCompany?->name,
            'date' => $o->order_date,                 
            'amount' => (string) $o->amount_total,
            'status' => $o->status,
        ];
    })->all();

    return ServiceResult::success([
        'stats' => [
            'total_orders' => MaterialOrder::query()->count(),
            'completed_orders' => MaterialOrder::query()->where('status', MaterialOrder::STATUS_COMPLETED)->count(),
            'total_sales' => round((float) MaterialOrder::query()->sum('amount_total'), 2),
        ],
        'data' => [
            'items' => $items,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ],
    ], 'Material orders fetched successfully');
}

    public function show(int $orderId): array
    {
        $order = $this->materialOrderRepository->findById($orderId);

        if (!$order) {
            return ServiceResult::error('Material order not found', null, null, 404);
        }

        return ServiceResult::success($order, 'Material order fetched successfully');
    }

    public function destroy(int $orderId): array
    {
        $order = $this->materialOrderRepository->findById($orderId);

        if (!$order) {
            return ServiceResult::error('Material order not found', null, null, 404);
        }

        $this->materialOrderRepository->delete($order);

        return ServiceResult::success(null, 'Material order deleted successfully');
    }
}
