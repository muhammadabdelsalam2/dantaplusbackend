<?php

namespace App\Repositories;

use App\Models\MaterialOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaterialOrderRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
{
    return MaterialOrder::query()
        ->select(['id','order_code','clinic_id','supplier_company_id','order_date','amount_total','status'])
        ->with([
            'clinic:id,name',
            'supplierCompany:id,name',
        ])
        ->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhereHas('clinic', fn($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('supplierCompany', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        })
        ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
        ->orderByDesc('id')
        ->paginate($perPage);
}

    public function findById(int $orderId): ?MaterialOrder
    {
        return MaterialOrder::with([
            'clinic:id,name,email,phone',
            'supplierCompany:id,name,email,phone',
            'items.product:id,company_id,name,category,price,image_url',
        ])->find($orderId);
    }

    public function delete(MaterialOrder $order): void
    {
        $order->delete();
    }
}
