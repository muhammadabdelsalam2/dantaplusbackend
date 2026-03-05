<?php

namespace App\Repositories;

use App\Models\MaterialCompany;
use App\Models\MaterialProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MaterialCompanyRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MaterialCompany::query()
            ->withCount('products')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['country'] ?? null, fn($query, $country) => $query->where('country', $country))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $companyId, array $with = []): ?MaterialCompany
    {
        return MaterialCompany::with($with)->find($companyId);
    }

    public function create(array $data): MaterialCompany
    {
        return MaterialCompany::create($data);
    }

    public function update(MaterialCompany $company, array $data): MaterialCompany
    {
        $company->update($data);

        return $company->refresh();
    }

    public function delete(MaterialCompany $company): void
    {
        $company->delete();
    }

    public function stats(): array
    {
        $totalCompanies = MaterialCompany::count();
        $totalProducts = MaterialProduct::count();
        $totalInventoryValue = MaterialProduct::query()
            ->selectRaw('COALESCE(SUM(price * stock), 0) as total')
            ->value('total');

        return [
            'total_companies' => (int) $totalCompanies,
            'total_products' => (int) $totalProducts,
            'total_inventory_value' => (float) $totalInventoryValue,
        ];
    }

    public function commissionRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MaterialCompany::query()
            ->leftJoin('material_orders', 'material_orders.supplier_company_id', '=', 'material_companies.id')
            ->select([
                'material_companies.id',
                'material_companies.name',
                'material_companies.status',
                'material_companies.commission_percentage',
                'material_companies.last_commission_update',
                DB::raw('COALESCE(SUM(material_orders.amount_total), 0) as total_sales'),
                DB::raw('COALESCE(SUM(material_orders.commission_amount), 0) as commission_earned'),
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('material_companies.name', 'like', "%{$search}%")
                        ->orWhere('material_companies.email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('material_companies.status', $status))
            ->groupBy([
                'material_companies.id',
                'material_companies.name',
                'material_companies.status',
                'material_companies.commission_percentage',
                'material_companies.last_commission_update',
            ])
            ->orderByDesc('material_companies.id')
            ->paginate($perPage);
    }

    public function commissionTotals(): array
    {
        $totals = DB::table('material_orders')
            ->selectRaw('COALESCE(SUM(amount_total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as total_commission_earned')
            ->first();

        return [
            'total_sales' => (float) ($totals->total_sales ?? 0),
            'total_commission_earned' => (float) ($totals->total_commission_earned ?? 0),
            'total_companies' => MaterialCompany::count(),
            'active_companies' => MaterialCompany::where('status', MaterialCompany::STATUS_ACTIVE)->count(),
        ];
    }
}
