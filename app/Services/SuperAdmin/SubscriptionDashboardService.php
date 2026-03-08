<?php

namespace App\Services\SuperAdmin;

use App\Repositories\Contracts\SuperAdmin\SubscriptionDashboardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionDashboardService
{
    private const PLAN_AMOUNTS = [
        'Basic' => 500.00,
        'Standard' => 1000.00,
        'Premium' => 1500.00,
    ];

    public function __construct(
        private SubscriptionDashboardRepositoryInterface $repo
    ) {}

    public function dashboard(): array
    {
        $rows = $this->repo->getSummarySource();

        $totalRevenue = $rows->sum(fn ($clinic) => $this->planAmount($clinic->subscription_plan));

        $outstandingPayments = $rows
            ->filter(fn ($clinic) => in_array($clinic->status, ['Expired', 'Suspended'], true))
            ->sum(fn ($clinic) => $this->planAmount($clinic->subscription_plan));

        $activeSubscriptions = $rows
            ->filter(fn ($clinic) => $clinic->status === 'Active')
            ->count();

        return [
            'summary' => [
                'total_subscription_revenue' => $this->normalizeMoney($totalRevenue),
                'outstanding_payments' => $this->normalizeMoney($outstandingPayments),
                'active_subscriptions' => $activeSubscriptions,
            ],
        ];
    }

    public function index(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $repoFilters = [
            'search' => $filters['search'] ?? null,
            'plan' => $filters['plan'] ?? null,
            'clinic_statuses' => $this->mapDashboardStatusToClinicStatuses($filters['status'] ?? null),
        ];

        $paginator = $this->repo->paginateForDashboard($repoFilters, $perPage);

        $items = collect($paginator->items())->map(function ($clinic) {
            return [
                'invoice_id' => $this->makeInvoiceId($clinic->id),
                'client' => $clinic->name,
                'type' => 'Clinic',
                'plan' => $clinic->subscription_plan,
                'due_date' => optional($clinic->expiry_date)?->toDateString(),
                'amount' => $this->normalizeMoney($this->planAmount($clinic->subscription_plan)),
                'status' => $this->mapClinicStatusToDashboardStatus($clinic->status),

                // useful extra fields
                'clinic_id' => $clinic->id,
                'owner_name' => $clinic->owner_name,
                'email' => $clinic->email,
                'payment_method' => $clinic->payment_method,
                'start_date' => optional($clinic->start_date)?->toDateString(),
                'clinic_status' => $clinic->status,
            ];
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    private function planAmount(?string $plan): float
    {
        return (float) (self::PLAN_AMOUNTS[$plan] ?? 0);
    }

    private function normalizeMoney(float|int $value): float
    {
        return round((float) $value, 2);
    }

    private function makeInvoiceId(int $clinicId): string
    {
        return 'INV-CL-' . str_pad((string) $clinicId, 4, '0', STR_PAD_LEFT);
    }

    private function mapClinicStatusToDashboardStatus(?string $clinicStatus): string
    {
        return match ($clinicStatus) {
            'Active' => 'Paid',
            'Trial' => 'Pending',
            'Expired', 'Suspended' => 'Overdue',
            default => 'Pending',
        };
    }

    private function mapDashboardStatusToClinicStatuses(?string $dashboardStatus): ?array
    {
        return match ($dashboardStatus) {
            'Paid' => ['Active'],
            'Pending' => ['Trial'],
            'Overdue' => ['Expired', 'Suspended'],
            default => null,
        };
    }
}
