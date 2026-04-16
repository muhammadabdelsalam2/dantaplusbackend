<?php

namespace App\Services\Company;

use App\Models\CompanyExpense;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function ordersByMonth(): array
    {
        return Order::query()
            ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month_key, COUNT(*) as total_orders, SUM(total_amount) as total_amount')
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->map(fn ($row) => ['month' => $row->month_key, 'total_orders' => (int) $row->total_orders, 'total_amount' => (float) $row->total_amount])
            ->all();
    }

    public function revenueByClinic(): array
    {
        return Order::query()
            ->select('clinic_id', DB::raw('SUM(total_amount) as revenue'))
            ->whereNotNull('clinic_id')
            ->with('clinic:id,name')
            ->groupBy('clinic_id')
            ->get()
            ->map(fn ($row) => ['clinic_id' => $row->clinic_id, 'clinic_name' => $row->clinic?->name, 'revenue' => (float) $row->revenue])
            ->all();
    }

    public function mostRequestedMaterials(): array
    {
        return DB::table('material_order_items')
            ->join('material_orders', 'material_orders.id', '=', 'material_order_items.order_id')
            ->join('material_products', 'material_products.id', '=', 'material_order_items.product_id')
            ->where('material_orders.company_id', auth()->user()->company_id)
            ->selectRaw('material_products.id, material_products.name, SUM(material_order_items.quantity) as total_quantity')
            ->groupBy('material_products.id', 'material_products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['product_id' => $row->id, 'product_name' => $row->name, 'total_quantity' => (int) $row->total_quantity])
            ->all();
    }
}
