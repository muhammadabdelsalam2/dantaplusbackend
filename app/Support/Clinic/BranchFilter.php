<?php

namespace App\Support\Clinic;

trait BranchFilter
{
    protected function selectedBranchId(array $filters): ?int
    {
        $branchId = $filters['branch_id'] ?? null;

        if ($branchId === null || $branchId === '' || strtolower((string) $branchId) === 'all') {
            return null;
        }

        return (int) $branchId;
    }
}
