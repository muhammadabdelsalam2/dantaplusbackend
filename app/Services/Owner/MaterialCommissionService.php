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
            'items' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'totals' => $this->materialCompanyRepository->commissionTotals(),
        ];

        return ServiceResult::success($data, 'Material commissions fetched successfully');
    }
}
