<?php

namespace App\Support\Clinic;

trait BranchFilter
{
    protected function selectedBranchId(array $filters): ?int
    {
        $branchId = $filters['branch_id'] ?? app(BranchContext::class)->id();

        if ($branchId === null || $branchId === '' || strtolower((string) $branchId) === 'all') {
            return null;
        }

        return (int) $branchId;
    }

    protected function branchContext(): BranchContext
    {
        return app(BranchContext::class);
    }
}
