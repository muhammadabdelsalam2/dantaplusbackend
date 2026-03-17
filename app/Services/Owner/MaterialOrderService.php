<?php

namespace App\Services\Owner;

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
            'code' => $o->order_code,
            'clinic_name' => $o->clinic?->name,
            'supplier_name' => $o->supplierCompany?->name,
            'date' => $o->order_date,                 
            'amount' => (string) $o->amount_total,
            'status' => $o->status,
        ];
    })->all();

    return ServiceResult::success([
        'items' => $items,
        'pagination' => [
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
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
