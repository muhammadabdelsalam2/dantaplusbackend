<?php

namespace App\Services\Company;

use App\Http\Resources\Company\DashboardResource;
use App\Models\Conversation;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function overview(): array
    {
        $companyId = auth()->user()->company_id;

        $kpis = [
            'total_orders' => Order::query()->count(),
            'processing_orders' => Order::query()->whereIn('status', ['Processing', 'Confirmed', 'Shipped'])->count(),
            'completed_orders' => Order::query()->where('status', 'Delivered')->count(),
            'pending_payments' => Order::query()->where('payment_status', 'Pending')->count(),
        ];

        $topClinic = Order::query()
            ->select('clinic_id', DB::raw('COUNT(*) as total_orders'))
            ->whereNotNull('clinic_id')
            ->with('clinic:id,name')
            ->groupBy('clinic_id')
            ->orderByDesc('total_orders')
            ->first();

        $trends = Order::query()
            ->selectRaw('DATE(order_date) as order_day, COUNT(*) as total')
            ->whereNotNull('order_date')
            ->groupBy('order_day')
            ->orderBy('order_day')
            ->limit(30)
            ->get()
            ->map(fn ($row) => ['date' => $row->order_day, 'total_orders' => (int) $row->total]);

        $lowStock = InventoryItem::query()
            ->whereColumn('quantity', '<=', 'minimum_stock_level')
            ->orderBy('quantity')
            ->limit(5)
            ->get(['id', 'product_name', 'quantity', 'minimum_stock_level']);

        return (new DashboardResource([
            'kpis' => $kpis,
            'top_clinic' => $topClinic ? [
                'id' => $topClinic->clinic_id,
                'name' => $topClinic->clinic?->name,
                'total_orders' => (int) $topClinic->total_orders,
            ] : null,
            'order_trends' => $trends,
            'low_stock_alerts' => $lowStock,
            'company_id' => $companyId,
        ]))->resolve();
    }

    public function orderTrends(): array
    {
        return Order::query()
            ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month_key, COUNT(*) as total_orders, SUM(total_amount) as total_amount')
            ->whereNotNull('order_date')
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month_key,
                'total_orders' => (int) $row->total_orders,
                'total_amount' => (float) $row->total_amount,
            ])
            ->all();
    }
}
