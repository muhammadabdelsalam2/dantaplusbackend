<?php

namespace App\Services\Lab;

use App\Http\Resources\CaseResource;
use App\Models\CaseModel;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    // -------------------------------------------------------------------------
    // Stats Cards
    // -------------------------------------------------------------------------

    public function getStats(): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $now = now();

        // Active Cases = any status that is NOT Completed or Delivered
        $activeCases = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->count();

        $activeCasesThisWeek = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->count();

        // Completed This Month
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

        // Pending Deliveries = Completed but not yet Delivered
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

        // Monthly Revenue — sum of invoices for this month (adjust model/table as needed)
        // Assumes an `invoices` table with `lab_id`, `total_amount`, `issued_at`
        $monthlyRevenue = DB::table('invoices')
            ->where('lab_id', $labId)
            ->whereMonth('issued_at', $now->month)
            ->whereYear('issued_at', $now->year)
            ->sum('total_amount');

        $lastMonthRevenue = DB::table('invoices')
            ->where('lab_id', $labId)
            ->whereMonth('issued_at', $now->copy()->subMonth()->month)
            ->whereYear('issued_at', $now->copy()->subMonth()->year)
            ->sum('total_amount');

        $revenueGrowth = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($monthlyRevenue > 0 ? 100 : 0);

        return ServiceResult::success([
            'active_cases' => [
                'value'         => $activeCases,
                'change_label'  => ($activeCasesThisWeek >= 0 ? '+' : '') . $activeCasesThisWeek . ' this week',
            ],
            'completed_this_month' => [
                'value'         => $completedThisMonth,
                'change_label'  => ($completedGrowth >= 0 ? '+' : '') . $completedGrowth . '%',
            ],
            'pending_deliveries' => [
                'value'         => $pendingDeliveries,
                'change_label'  => ($pendingDeliveriesDiff >= 0 ? '+' : '') . $pendingDeliveriesDiff,
            ],
            'monthly_revenue' => [
                'value'         => round($monthlyRevenue, 2),
                'change_label'  => ($revenueGrowth >= 0 ? '+' : '') . $revenueGrowth . '%',
            ],
        ], 'Dashboard stats fetched successfully');
    }

    // -------------------------------------------------------------------------
    // Charts
    // -------------------------------------------------------------------------

    public function getCharts(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $year  = (int) ($filters['year']  ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        return ServiceResult::success([
            'case_type_distribution' => $this->caseTypeDistribution($labId),
            'monthly_revenue'        => $this->monthlyRevenueChart($labId, $year),
            'cases_by_clinic'        => $this->casesByClinic($labId, $year, $month),
            'wip_by_technician'      => $this->wipByTechnician($labId),
        ], 'Dashboard charts fetched successfully');
    }

    /**
     * Donut chart — case counts grouped by case_type (all time or current year).
     */
    private function caseTypeDistribution(int $labId): array
    {
        $rows = CaseModel::query()
            ->select('case_type', DB::raw('COUNT(*) as total'))
            ->where('lab_id', $labId)
            ->whereYear('created_at', now()->year)
            ->groupBy('case_type')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'label' => $r->case_type,
            'value' => (int) $r->total,
        ])->values()->all();
    }

    /**
     * Line/bar chart — monthly revenue for the given year, one point per month.
     */
    private function monthlyRevenueChart(int $labId, int $year): array
    {
        $rows = DB::table('invoices')
            ->select(DB::raw('MONTH(issued_at) as month'), DB::raw('SUM(total_amount) as revenue'))
            ->where('lab_id', $labId)
            ->whereYear('issued_at', $year)
            ->groupBy(DB::raw('MONTH(issued_at)'))
            ->orderBy(DB::raw('MONTH(issued_at)'))
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = [
                'month'   => $m,
                'label'   => now()->setMonth($m)->format('M'),
                'revenue' => isset($rows[$m]) ? round($rows[$m]->revenue, 2) : 0,
            ];
        }

        return $months;
    }

    /**
     * Bar chart — number of cases per clinic for the given month.
     */
    private function casesByClinic(int $labId, int $year, int $month): array
    {
        return CaseModel::query()
            ->select('clinic_id', DB::raw('COUNT(*) as total'))
            ->with('clinic:id,name')
            ->where('lab_id', $labId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('clinic_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'clinic_id'   => $r->clinic_id,
                'clinic_name' => $r->clinic?->name ?? 'Unknown',
                'total'       => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    /**
     * WIP chart — cases In Progress grouped by assigned technician.
     */
    private function wipByTechnician(int $labId): array
    {
        return CaseModel::query()
            ->select('assigned_technician_id', DB::raw('COUNT(*) as total'))
            ->with('technician:id,name')
            ->where('lab_id', $labId)
            ->where('status', CaseModel::STATUS_IN_PROGRESS)
            ->groupBy('assigned_technician_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'technician_id'   => $r->assigned_technician_id,
                'technician_name' => $r->technician?->name ?? 'Unassigned',
                'total'           => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Active Cases Table
    // -------------------------------------------------------------------------

    public function getActiveCases(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $search  = $filters['search'] ?? null;
        $status  = $filters['status'] ?? null;

        $cases = CaseModel::query()
            ->with([
                'clinic:id,name',
                'technician:id,name',
            ])
            ->where('lab_id', $labId)
            // If no status filter passed, show everything except Delivered
            ->when(
                $status,
                fn ($q) => $q->where('status', $status),
                fn ($q) => $q->where('status', '!=', CaseModel::STATUS_DELIVERED)
            )
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('case_number', 'like', "%{$search}%")
                          ->orWhere('case_type',   'like', "%{$search}%")
                          ->orWhereHas('clinic', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return ServiceResult::success([
            'items' => collect($cases->items())->map(fn ($case) => [
                'id'             => $case->id,
                'case_number'    => $case->case_number,
                'clinic_name'    => $case->clinic?->name,
                'case_type'      => $case->case_type,
                'technician'     => $case->technician?->name ?? 'Unassigned',
                'status'         => $case->status,
                'priority'       => $case->priority,
                'due_date'       => $case->due_date?->format('d/m/Y'),
            ])->values()->all(),
            'pagination' => [
                'current_page' => $cases->currentPage(),
                'last_page'    => $cases->lastPage(),
                'per_page'     => $cases->perPage(),
                'total'        => $cases->total(),
            ],
        ], 'Active cases fetched successfully');
    }
}