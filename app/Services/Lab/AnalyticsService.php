<?php

namespace App\Services\Lab;

use App\Http\Resources\CaseResource;
use App\Models\CaseModel;
use App\Models\LabService;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function overview(array $filters = []): array
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $cases = $this->baseCaseQuery($labId, $filters)
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $servicePrices = LabService::query()
            ->where('lab_id', $labId)
            ->pluck('price', 'service_name')
            ->map(fn ($price) => (float) $price);

        $completedCases = $this->completedCases($cases);
        $totalRevenue = $cases->sum(fn (CaseModel $case) => $this->casePrice($case, $servicePrices));
        $completedRevenue = $completedCases->sum(fn (CaseModel $case) => $this->casePrice($case, $servicePrices));

        $perPage = max(1, min((int) ($filters['per_page'] ?? 100), 200));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginatedCases = $this->paginateCollection($cases, $page, $perPage);

        return ServiceResult::success([
            'filters' => [
                'startDate' => $filters['startDate'] ?? null,
                'endDate' => $filters['endDate'] ?? null,
                'clinicId' => $filters['clinicId'] ?? 'all',
                'dentistId' => $filters['dentistId'] ?? 'all',
                'caseType' => $filters['caseType'] ?? 'all',
            ],
            'stats' => [
                'totalCompletedCases' => $completedCases->count(),
                'activeCases' => $cases
                    ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
                    ->count(),
                'averageRevenuePerCase' => $completedCases->count() > 0
                    ? round($completedRevenue / $completedCases->count(), 2)
                    : 0,
                'averageTurnaroundTime' => $this->averageTurnaroundTime($completedCases),
            ],
            'caseTypeBreakdown' => $this->caseTypeBreakdown($cases),
            'monthlyCompletedCases' => $this->monthlyCompletedCases($completedCases),
            'performanceOverview' => $this->performanceOverview($cases, $servicePrices),
            'detailedCaseList' => [
                'items' => CaseResource::collection($paginatedCases['items'])->resolve(),
                'pagination' => $paginatedCases['pagination'],
            ],
            'totals' => [
                'totalCases' => $cases->count(),
                'completedCases' => $completedCases->count(),
                'totalRevenue' => round($totalRevenue, 2),
                'completedRevenue' => round($completedRevenue, 2),
            ],
        ], 'Lab analytics fetched successfully');
    }

    private function baseCaseQuery(int $labId, array $filters): Builder
    {
        return CaseModel::query()
            ->with([
                'clinic:id,name',
                'lab:id,name',
                'patient:id,user_id',
                'patient.user:id,name',
                'dentist:id,user_id',
                'dentist.user:id,name',
                'technician:id,name',
                'deliveryRep:id,name',
            ])
            ->where('lab_id', $labId)
            ->when($filters['startDate'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['endDate'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))
            ->when(($filters['clinicId'] ?? 'all') !== 'all', fn (Builder $q) => $q->where('clinic_id', (int) $filters['clinicId']))
            ->when(($filters['dentistId'] ?? 'all') !== 'all', fn (Builder $q) => $q->where('dentist_id', (int) $filters['dentistId']))
            ->when(($filters['caseType'] ?? 'all') !== 'all', fn (Builder $q) => $q->where('case_type', $filters['caseType']))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status));
    }

    private function completedCases(Collection $cases): Collection
    {
        return $cases
            ->filter(fn (CaseModel $case) => in_array($case->status, [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED], true))
            ->values();
    }

    private function casePrice(CaseModel $case, Collection $servicePrices): float
    {
        return round((float) ($servicePrices[$case->case_type] ?? 0), 2);
    }

    private function averageTurnaroundTime(Collection $completedCases): float
    {
        $durations = $completedCases
            ->map(function (CaseModel $case) {
                $finishedAt = $case->delivered_at ?? $case->completed_at;
                if (! $finishedAt || ! $case->created_at) {
                    return null;
                }

                return Carbon::parse($case->created_at)->diffInHours(Carbon::parse($finishedAt)) / 24;
            })
            ->filter(fn ($days) => $days !== null)
            ->values();

        return $durations->count() > 0 ? round((float) $durations->avg(), 1) : 0;
    }

    private function caseTypeBreakdown(Collection $cases): array
    {
        $total = $cases->count();
        if ($total === 0) {
            return [];
        }

        return $cases
            ->groupBy('case_type')
            ->map(fn (Collection $group, string $type) => [
                'name' => $type,
                'value' => $group->count(),
                'percent' => (string) round(($group->count() / $total) * 100),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function monthlyCompletedCases(Collection $completedCases): array
    {
        return $completedCases
            ->filter(fn (CaseModel $case) => $case->delivered_at || $case->completed_at || $case->updated_at)
            ->groupBy(function (CaseModel $case) {
                $date = Carbon::parse($case->delivered_at ?? $case->completed_at ?? $case->updated_at);

                return $date->format('Y-m');
            })
            ->map(function (Collection $group, string $monthKey) {
                $date = Carbon::createFromFormat('Y-m', $monthKey);

                return [
                    'name' => $date->format('M y'),
                    'month' => $date->month,
                    'year' => $date->year,
                    'Completed Cases' => $group->count(),
                ];
            })
            ->sortBy(fn (array $row) => sprintf('%04d-%02d', $row['year'], $row['month']))
            ->values()
            ->all();
    }

    private function performanceOverview(Collection $cases, Collection $servicePrices): array
    {
        return $cases
            ->groupBy(fn (CaseModel $case) => $case->clinic_id . '|' . $case->dentist_id)
            ->map(function (Collection $group) use ($servicePrices) {
                /** @var CaseModel $first */
                $first = $group->first();
                $completed = $this->completedCases($group);
                $completedWithFinishedDate = $completed->filter(fn (CaseModel $case) => $case->delivered_at || $case->completed_at);
                $onTime = $completedWithFinishedDate->filter(function (CaseModel $case) {
                    $finishedAt = $case->delivered_at ?? $case->completed_at;

                    return $finishedAt && $case->due_date && Carbon::parse($finishedAt)->lte(Carbon::parse($case->due_date)->endOfDay());
                });

                $caseTypeCounts = $group->groupBy('case_type')->map->count();

                return [
                    'clinicId' => (string) $first->clinic_id,
                    'clinicName' => $first->clinic?->name ?? 'Unknown',
                    'dentistId' => (string) $first->dentist_id,
                    'dentistName' => $first->dentist?->user?->name ?? 'Unknown',
                    'totalCases' => $group->count(),
                    'completedCases' => $completed->count(),
                    'onTimeRate' => $completedWithFinishedDate->count() > 0
                        ? round(($onTime->count() / $completedWithFinishedDate->count()) * 100, 1)
                        : 0,
                    'avgDuration' => $this->averageTurnaroundTime($completed),
                    'mostCommonCaseType' => $caseTypeCounts->sortDesc()->keys()->first() ?? 'N/A',
                    'totalRevenue' => round($group->sum(fn (CaseModel $case) => $this->casePrice($case, $servicePrices)), 2),
                ];
            })
            ->sortByDesc('totalCases')
            ->values()
            ->all();
    }

    private function paginateCollection(Collection $items, int $page, int $perPage): array
    {
        $total = $items->count();
        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'items' => $items->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }
}
