<?php

namespace App\Services\Lab;

use App\Models\CaseModel;
use App\Models\LabPayment;
use App\Services\Lab\Accounting\LabAccountingService;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private LabAccountingService $accountingService)
    {
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    public function getStats(): array
    {
        $labId = $this->currentLabId();

        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $now = now();

        // Active cases
        $activeCases = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->count();

        $activeCasesThisWeek = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->count();

        // Completed this month
        $completedThisMonth = CaseModel::query()
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_COMPLETED)
            ->whereMonth('completed_at', $now->month)
            ->whereYear('completed_at', $now->year)
            ->count();

        $completedLastMonth = CaseModel::query()
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_COMPLETED)
            ->whereMonth('completed_at', $now->copy()->subMonth()->month)
            ->whereYear('completed_at', $now->copy()->subMonth()->year)
            ->count();

        $completedGrowth = $completedLastMonth > 0
            ? round((($completedThisMonth - $completedLastMonth) / $completedLastMonth) * 100, 1)
            : ($completedThisMonth > 0 ? 100 : 0);

        // Pending deliveries
        $pendingDeliveries = CaseModel::query()
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_COMPLETED)
            ->count();

        $pendingDeliveriesLastWeek = CaseModel::query()
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_COMPLETED)
            ->where('created_at', '<', $now->copy()->startOfWeek())
            ->count();

        $pendingDeliveriesDiff = $pendingDeliveries - $pendingDeliveriesLastWeek;

        $accountingSummary = $this->accountingService->dashboardSummary($labId, $now->format('Y-m'));
        $lastMonthAccountingSummary = $this->accountingService->dashboardSummary($labId, $now->copy()->subMonthNoOverflow()->format('Y-m'));

        $monthlyRevenue = $accountingSummary['monthly_income'];
        $lastMonthRevenue = $lastMonthAccountingSummary['monthly_income'];

        $revenueGrowth = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($monthlyRevenue > 0 ? 100 : 0);

        return ServiceResult::success([
            'active_cases' => [
                'value' => $activeCases,
                'change_label' => ($activeCasesThisWeek >= 0 ? '+' : '') . $activeCasesThisWeek . ' this week',
            ],
            'completed_this_month' => [
                'value' => $completedThisMonth,
                'change_label' => ($completedGrowth >= 0 ? '+' : '') . $completedGrowth . '%',
            ],
            'pending_deliveries' => [
                'value' => $pendingDeliveries,
                'change_label' => ($pendingDeliveriesDiff >= 0 ? '+' : '') . $pendingDeliveriesDiff,
            ],
            'monthly_revenue' => [
                'value' => round($monthlyRevenue, 2),
                'change_label' => ($revenueGrowth >= 0 ? '+' : '') . $revenueGrowth . '%',
            ],
            'monthly_income' => [
                'value' => $accountingSummary['monthly_income'],
                'change_label' => ($revenueGrowth >= 0 ? '+' : '') . $revenueGrowth . '%',
            ],
            'monthly_expenses' => [
                'value' => $accountingSummary['monthly_expenses'],
                'change_label' => '',
            ],
            'monthly_profit' => [
                'value' => $accountingSummary['monthly_profit'],
                'change_label' => '',
            ],
            'total_outstanding' => [
                'value' => $accountingSummary['total_outstanding'],
                'change_label' => '',
            ],
        ], 'Dashboard stats fetched successfully');
    }

    public function getCharts(array $filters = []): array
    {
        $labId = $this->currentLabId();

        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $year  = (int) ($filters['year']  ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        $monthlyRevenue = $this->monthlyRevenueChart($labId, $year);

        return ServiceResult::success([
            'case_type_distribution' => $this->caseTypeDistribution($labId),
            'monthly_revenue'        => $monthlyRevenue,
            'monthly_revenue_chart'  => $monthlyRevenue,
            'cases_by_clinic'        => $this->casesByClinic($labId, $year, $month),
            'wip_by_technician'      => $this->wipByTechnician($labId),
            ...$this->accountingService->dashboardCharts($labId, $year),
        ], 'Dashboard charts fetched successfully');
    }

    public function getActiveCases(array $filters): array
    {
        $labId = $this->currentLabId();

        if (! $labId) {
            return ServiceResult::error('Lab account is not linked', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $query = CaseModel::query()
            ->with(['clinic:id,name', 'patient.user:id,name', 'technician:id,name'])
            ->where('lab_id', $labId)
            ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->orderByDesc('id');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%$search%")
                  ->orWhere('case_type', 'like', "%$search%")
                  ->orWhereHas('clinic', fn ($c) => $c->where('name', 'like', "%$search%"))
                  ->orWhereHas('patient.user', fn ($p) => $p->where('name', 'like', "%$search%"));
            });
        }

        $cases = $query->paginate($perPage);

        return ServiceResult::success([
            'data' => $cases->items(),
            'meta' => [
                'page'      => $cases->currentPage(),
                'per_page'  => $cases->perPage(),
                'total'     => $cases->total(),
                'last_page' => $cases->lastPage(),
            ]
        ], 'Active cases fetched');
    }

    // -------------------------------------------------------------------------
    // PRIVATE CHART HELPERS
    // -------------------------------------------------------------------------

    private function caseTypeDistribution(int $labId): array
    {
        return CaseModel::query()
            ->select('case_type', DB::raw('COUNT(*) as total'))
            ->where('lab_id', $labId)
            ->whereYear('created_at', now()->year)
            ->groupBy('case_type')
            ->get()
            ->map(fn ($r) => [
                'label' => $r->case_type,
                'value' => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    private function monthlyRevenueChart(int $labId, int $year): array
    {
        $payments = LabPayment::query()
            ->where('lab_id', $labId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->setYear($year)->startOfYear(), now()->setYear($year)->endOfYear()])
            ->get();

        return collect(range(1, 12))
            ->map(fn (int $month) => [
                'month' => $month,
                'total' => round((float) $payments
                    ->filter(fn (LabPayment $payment) => (int) optional($payment->paid_at)->month === $month)
                    ->sum('amount'), 2),
            ])
            ->all();
    }

    private function casesByClinic(int $labId, int $year, int $month): array
    {
        return CaseModel::query()
            ->with('clinic:id,name')
            ->where('lab_id', $labId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get()
            ->groupBy('clinic.name')
            ->map(fn ($g) => $g->count())
            ->toArray();
    }

    private function wipByTechnician(int $labId): array
    {
        return CaseModel::query()
            ->with('technician:id,name')
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_IN_PROGRESS)
            ->get()
            ->groupBy('technician.name')
            ->map(fn ($g) => $g->count())
            ->toArray();
    }
}
