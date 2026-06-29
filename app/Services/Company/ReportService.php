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
    public function generateReport(array $filters): array
{
    $clinicId  = $filters['clinic_id'] ?? null;
    $dateFrom  = $filters['date_from'] ?? null;
    $dateTo    = $filters['date_to'] ?? null;
    $companyId = auth()->user()->company_id;

    // Orders Summary
    $ordersQuery = Order::query()
        ->where('company_id', $companyId)
        ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
        ->when($dateFrom,  fn($q) => $q->whereDate('order_date', '>=', $dateFrom))
        ->when($dateTo,    fn($q) => $q->whereDate('order_date', '<=', $dateTo));

    $totalOrders  = (clone $ordersQuery)->count();
    $totalRevenue = (clone $ordersQuery)->sum('total_amount');
    $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

    // Orders by Status
    $byStatus = (clone $ordersQuery)
        ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
        ->groupBy('status')
        ->get()
        ->map(fn($r) => [
            'status' => $r->status,
            'count'  => (int) $r->count,
            'total'  => (float) $r->total,
        ]);

    // Orders by Month
    $byMonth = (clone $ordersQuery)
        ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month, COUNT(*) as total_orders, SUM(total_amount) as total_amount')
        ->groupBy('month')
        ->orderBy('month')
        ->get()
        ->map(fn($r) => [
            'month'        => $r->month,
            'total_orders' => (int) $r->total_orders,
            'total_amount' => (float) $r->total_amount,
        ]);

    // Most Requested Materials في الفترة دي
    $topMaterials = DB::table('material_order_items')
        ->join('material_orders', 'material_orders.id', '=', 'material_order_items.order_id')
        ->join('material_products', 'material_products.id', '=', 'material_order_items.product_id')
        ->where('material_orders.company_id', $companyId)
        ->when($clinicId, fn($q) => $q->where('material_orders.clinic_id', $clinicId))
        ->when($dateFrom,  fn($q) => $q->whereDate('material_orders.created_at', '>=', $dateFrom))
        ->when($dateTo,    fn($q) => $q->whereDate('material_orders.created_at', '<=', $dateTo))
        ->selectRaw('material_products.id, material_products.name, SUM(material_order_items.quantity) as total_quantity, SUM(material_order_items.quantity * material_order_items.unit_price) as total_revenue')
        ->groupBy('material_products.id', 'material_products.name')
        ->orderByDesc('total_quantity')
        ->limit(10)
        ->get()
        ->map(fn($r) => [
            'product_id'    => $r->id,
            'product_name'  => $r->name,
            'total_quantity'=> (int) $r->total_quantity,
            'total_revenue' => (float) $r->total_revenue,
        ]);

    // Clinic Info لو فيه فلتر
    $clinicInfo = null;
    if ($clinicId) {
        $clinic = \App\Models\Clinic::select('id', 'name', 'phone', 'email')
                    ->find($clinicId);
        $clinicInfo = $clinic;
    }

    return [
        'meta' => [
            'clinic'    => $clinicInfo,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'generated_at' => now()->toDateTimeString(),
        ],
        'summary' => [
            'total_orders'   => $totalOrders,
            'total_revenue'  => (float) $totalRevenue,
            'average_order'  => round($avgOrder, 2),
        ],
        'orders_by_status' => $byStatus,
        'orders_by_month'  => $byMonth,
        'top_materials'    => $topMaterials,
    ];
}
}
