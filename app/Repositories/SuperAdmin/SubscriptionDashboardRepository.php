<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Clinic;
use App\Repositories\Contracts\SuperAdmin\SubscriptionDashboardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubscriptionDashboardRepository implements SubscriptionDashboardRepositoryInterface
{
    public function paginateForDashboard(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Clinic::query()
            ->select([
                'id',
                'name',
                'owner_name',
                'email',
                'subscription_plan',
                'status',
                'start_date',
                'expiry_date',
                'payment_method',
                'max_users',
                'max_branches',
                'created_at',
            ])
            ->when(
                !empty($filters['clinic_statuses']),
                fn (Builder $query) => $query->whereIn('status', $filters['clinic_statuses'])
            )
            ->when(
                !empty($filters['plan']),
                fn (Builder $query) => $query->where('subscription_plan', $filters['plan'])
            );

        if ($search !== '') {
            $clinicId = $this->extractClinicIdFromSearch($search);

            $query->where(function (Builder $q) use ($search, $clinicId) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if ($clinicId !== null) {
                    $q->orWhere('id', $clinicId);
                }
            });
        }

        return $query
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function getSummarySource(): Collection
    {
        return Clinic::query()
            ->select([
                'id',
                'subscription_plan',
                'status',
                'expiry_date',
            ])
            ->get();
    }

    private function extractClinicIdFromSearch(string $search): ?int
    {
        if (preg_match('/(\d+)/', $search, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }
}
