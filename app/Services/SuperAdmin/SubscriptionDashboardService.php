<?php

namespace App\Services\SuperAdmin;

use App\Models\Clinic;
use App\Repositories\Contracts\SuperAdmin\SubscriptionDashboardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionDashboardService
{
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
            'cards' => [
                'active_subscriptions' => $activeSubscriptions,
                'outstanding_payments' => $this->normalizeMoney($outstandingPayments),
                'total_subscription_revenue' => $this->normalizeMoney($totalRevenue),
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
                'due_date' => optional($clinic->expiry_date)?->format('d/m/Y'),
                'amount' => $this->normalizeMoney($this->planAmount($clinic->subscription_plan)),
                'status' => $this->mapClinicStatusToDashboardStatus($clinic->status),
            ];
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    private function planAmount(?string $plan): float
    {
        return (float) config('subscriptions.plan_prices.' . strtolower((string) $plan), 0);
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

    public function updateStatus(string $invoiceId, string $status): array
    {
        $clinicId = $this->extractClinicIdFromInvoiceId($invoiceId);

        if ($clinicId === null) {
            return ['success' => false, 'message' => 'Invoice not found', 'code' => 404];
        }

        $clinic = Clinic::query()->find($clinicId);

        if (! $clinic) {
            return ['success' => false, 'message' => 'Invoice not found', 'code' => 404];
        }

        $clinic->update(['status' => $this->mapDashboardStatusToClinicStatus($status)]);

        return [
            'success' => true,
            'message' => 'Invoice status updated successfully',
            'code' => 200,
            'data' => [
                'invoice_id' => $this->makeInvoiceId($clinic->id),
                'status' => $status,
            ],
        ];
    }

    private function extractClinicIdFromInvoiceId(string $invoiceId): ?int
    {
        if (preg_match('/INV-CL-(\d+)/', $invoiceId, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function mapDashboardStatusToClinicStatus(string $status): string
    {
        return match ($status) {
            'Paid' => 'Active',
            'Overdue' => 'Expired',
            'Partially Paid', 'Pending' => 'Trial',
            default => 'Trial',
        };
    }
}
