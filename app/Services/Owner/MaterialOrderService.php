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
        'stats' => $this->stats(),
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

    private function stats(): array
    {
        return [
            'outstanding_invoices' => MaterialOrder::query()
                ->whereIn('payment_status', ['Pending', 'pending', 'pending_cash', 'pending_payment', 'pending_invoice'])
                ->where('status', '!=', MaterialOrder::STATUS_CANCELLED)
                ->count(),
            'total_material_revenue' => round((float) MaterialOrder::query()
                ->where('status', MaterialOrder::STATUS_COMPLETED)
                ->selectRaw('COALESCE(SUM(COALESCE(total_amount, amount_total, 0)), 0) as total')
                ->value('total'), 2),
            'total_orders_platform_wide' => MaterialOrder::query()->count(),
        ];
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
