<?php

namespace App\Services\Lab;

use App\Models\CaseModel;
use App\Models\Invoice;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    // ---------------------------------------------------------------------
    // STATS
    // ---------------------------------------------------------------------

    public function getStats(): array
    {
        $labId = $this->currentLabId();

        if (! $labId) {
            return ServiceResult::error(
                'Lab account is not linked to a dental lab',
                null,
                null,
                403
            );
        }

        $now = now();

        // Active cases
        $activeCases = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [
                CaseModel::STATUS_COMPLETED,
                CaseModel::STATUS_DELIVERED
            ])
            ->count();

        $activeCasesThisWeek = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [
                CaseModel::STATUS_COMPLETED,
                CaseModel::STATUS_DELIVERED
            ])
            ->whereBetween('created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek()
            ])
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

        // Pending deliveries (completed but not delivered)
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

        // -----------------------------------------------------------------
        // FIXED REVENUE (NO lab_id in invoices)
        // invoices -> clinic -> clinic_lab_partnerships -> lab_id
        // -----------------------------------------------------------------

        $monthlyRevenue = Invoice::query()
            ->whereHas('clinic.labPartnerships', function ($q) use ($labId) {
                $q->where('lab_id', $labId);
            })
            ->whereMonth('issue_date', $now->month)
            ->whereYear('issue_date', $now->year)
            ->sum('total_amount');

        $lastMonthRevenue = Invoice::query()
            ->whereHas('clinic.labPartnerships', function ($q) use ($labId) {
                $q->where('lab_id', $labId);
            })
            ->whereMonth('issue_date', $now->copy()->subMonth()->month)
            ->whereYear('issue_date', $now->copy()->subMonth()->year)
            ->sum('total_amount');

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
        ], 'Dashboard stats fetched successfully');
    }

    // ---------------------------------------------------------------------
    // CHARTS (unchanged logic but safe structure)
    // ---------------------------------------------------------------------

    public function getCharts(array $filters = []): array
    {
        $labId = $this->currentLabId();

        if (! $labId) {
            return ServiceResult::error(
                'Lab account is not linked to a dental lab',
                null,
                null,
                403
            );
        }

        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        return ServiceResult::success([
            'case_type_distribution' => $this->caseTypeDistribution($labId),
            'monthly_revenue' => $this->monthlyRevenueChart($labId, $year),
            'cases_by_clinic' => $this->casesByClinic($labId, $year, $month),
            'wip_by_technician' => $this->wipByTechnician($labId),
        ], 'Dashboard charts fetched successfully');
    }

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
        return Invoice::query()
            ->whereHas('clinic.labPartnerships', fn ($q) =>
                $q->where('lab_id', $labId)
            )
            ->whereYear('issue_date', $year)
            ->selectRaw('MONTH(issue_date) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
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