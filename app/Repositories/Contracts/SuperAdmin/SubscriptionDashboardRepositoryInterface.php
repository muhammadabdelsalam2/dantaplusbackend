<?php

namespace App\Repositories\Contracts\SuperAdmin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SubscriptionDashboardRepositoryInterface
{
    public function paginateForDashboard(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getSummarySource(): Collection;
}
