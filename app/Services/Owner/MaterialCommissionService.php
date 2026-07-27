<?php

namespace App\Services\Owner;

use App\Repositories\MaterialCompanyRepository;
use App\Support\ServiceResult;

class MaterialCommissionService
{
    public function __construct(private MaterialCompanyRepository $materialCompanyRepository)
    {
    }

    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $rows = $this->materialCompanyRepository->commissionRows($filters, $perPage);

        $data = [
            'stats' => $this->materialCompanyRepository->commissionTotals(),
            'data' => [
                'items' => collect($rows->items())->map(fn ($row) => [
                    'id' => $row->id,
                    'last_update' => optional($row->last_commission_update ?? $row->updated_at)->format('d/m/Y'),
                    'commission_earned' => round((float) $row->commission_earned, 2),
                    'total_sales' => round((float) $row->total_sales, 2),
                    'commission_percentage' => (float) $row->commission_percentage,
                    'status' => $row->status,
                    'company' => $row->name,
                ])->values()->all(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ],
        ];

        return ServiceResult::success($data, 'Material commissions fetched successfully');
    }
}
