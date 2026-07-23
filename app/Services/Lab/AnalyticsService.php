<?php

namespace App\Services\Lab;


use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\Doctor;
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

        $filters = $this->normalizeFilters($labId, $filters);
        $caseQuery = $this->baseCaseQuery($labId, $filters);
        $this->applySort($caseQuery, $filters, 'cases');
        $cases = $caseQuery->get();

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
                'from' => $filters['startDate'] ?? null,
                'to' => $filters['endDate'] ?? null,
                'clinic_id' => $filters['clinicId'] ?? 'all',
                'doctor_id' => $filters['dentistId'] ?? 'all',
                'case_type_id' => $filters['case_type_id'] ?? 'all',
                'case_type' => $filters['caseType'] ?? 'all',
            ],
            'stats' => [
                'total_completed_cases' => $completedCases->count(),
                'totalCompletedCases' => $completedCases->count(),
                'active_cases' => $cases
                    ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
                    ->count(),
                'activeCases' => $cases
                    ->whereNotIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
                    ->count(),
                'avg_revenue_per_case' => $completedCases->count() > 0
                    ? round($completedRevenue / $completedCases->count(), 2)
                    : 0,
                'averageRevenuePerCase' => $completedCases->count() > 0
                    ? round($completedRevenue / $completedCases->count(), 2)
                    : 0,
                'avg_turnaround_time' => $this->averageTurnaroundTime($completedCases),
                'averageTurnaroundTime' => $this->averageTurnaroundTime($completedCases),
            ],
            'caseTypeBreakdown' => $this->caseTypeBreakdown($cases),
            'monthlyCompletedCases' => $this->monthlyCompletedCases($completedCases),
            'performanceOverview' => $this->performanceOverview($cases, $servicePrices),
            'detailedCaseList' => [
                'items' => $this->detailedCaseRows($paginatedCases['items']),
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

    public function clinics(): array
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $clinicIds = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotNull('clinic_id')
            ->distinct()
            ->pluck('clinic_id');

        $clinics = Clinic::query()
            ->whereIn('id', $clinicIds)
            ->orWhereHas('labPartnerships', fn (Builder $q) => $q->where('lab_id', $labId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $clinic) => ['id' => $clinic->id, 'name' => $clinic->name])
            ->values()
            ->all();

        return ServiceResult::success($clinics, 'Lab analytics clinics fetched successfully');
    }

    public function doctors(array $filters = []): array
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $clinicId = $filters['clinic_id'] ?? $filters['clinicId'] ?? null;
        $clinicIds = CaseModel::query()
            ->where('lab_id', $labId)
            ->when($clinicId && $clinicId !== 'all', fn (Builder $q) => $q->where('clinic_id', (int) $clinicId))
            ->whereNotNull('clinic_id')
            ->distinct()
            ->pluck('clinic_id');

        $doctors = Doctor::query()
            ->whereHas('user', fn (Builder $q) => $q->whereIn('clinic_id', $clinicIds))
            ->with('user:id,name,clinic_id')
            ->get()
            ->sortBy(fn (Doctor $doctor) => $doctor->user?->name)
            ->map(fn (Doctor $doctor) => [
                'id' => $doctor->id,
                'user_id' => $doctor->user_id,
                'name' => $doctor->user?->name,
                'clinic_id' => $doctor->user?->clinic_id,
            ])
            ->values()
            ->all();

        return ServiceResult::success($doctors, 'Lab analytics doctors fetched successfully');
    }

    public function caseTypes(): array
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $serviceRows = LabService::query()
            ->where('lab_id', $labId)
            ->orderBy('service_name')
            ->get(['id', 'service_name']);

        $existingTypes = CaseModel::query()
            ->where('lab_id', $labId)
            ->whereNotNull('case_type')
            ->distinct()
            ->pluck('case_type');

        $types = $serviceRows
            ->map(fn (LabService $service) => ['id' => $service->id, 'name' => $service->service_name])
            ->concat($existingTypes->reject(fn ($type) => $serviceRows->contains('service_name', $type))->map(fn ($type) => ['id' => $type, 'name' => $type]))
            ->values()
            ->all();

        return ServiceResult::success($types, 'Lab analytics case types fetched successfully');
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

    private function normalizeFilters(int $labId, array $filters): array
    {
        $filters['startDate'] = $filters['startDate'] ?? $filters['from'] ?? null;
        $filters['endDate'] = $filters['endDate'] ?? $filters['to'] ?? null;
        $filters['clinicId'] = $filters['clinicId'] ?? $filters['clinic_id'] ?? 'all';
        $filters['dentistId'] = $filters['dentistId'] ?? $filters['doctor_id'] ?? $filters['doctorId'] ?? 'all';

        $caseType = $filters['caseType'] ?? $filters['case_type'] ?? null;
        $caseTypeId = $filters['case_type_id'] ?? null;
        if ($caseTypeId && $caseTypeId !== 'all') {
            $caseType = LabService::query()
                ->where('lab_id', $labId)
                ->where('id', $caseTypeId)
                ->value('service_name') ?? $caseTypeId;
        }
        $filters['caseType'] = $caseType ?: 'all';

        return $filters;
    }

    private function applySort(Builder $query, array $filters, string $context): void
    {
        $sortBy = $filters['sort_by'] ?? null;
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $columns = [
            'case_id' => 'case_number',
            'due_date' => 'due_date',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        if ($context === 'cases' && isset($columns[$sortBy])) {
            $query->orderBy($columns[$sortBy], $direction)->orderByDesc('id');
            return;
        }

        $query->orderBy('due_date')->orderByDesc('id');
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
        $rows = $cases
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
                    'clinic' => $first->clinic?->name ?? 'Unknown',
                    'clinicId' => (string) $first->clinic_id,
                    'clinicName' => $first->clinic?->name ?? 'Unknown',
                    'doctor' => $first->dentist?->user?->name ?? 'Unknown',
                    'dentistId' => (string) $first->dentist_id,
                    'dentistName' => $first->dentist?->user?->name ?? 'Unknown',
                    'total_cases' => $group->count(),
                    'totalCases' => $group->count(),
                    'completed' => $completed->count(),
                    'completedCases' => $completed->count(),
                    'on_time_percent' => $completedWithFinishedDate->count() > 0
                        ? round(($onTime->count() / $completedWithFinishedDate->count()) * 100, 1)
                        : 0,
                    'onTimeRate' => $completedWithFinishedDate->count() > 0
                        ? round(($onTime->count() / $completedWithFinishedDate->count()) * 100, 1)
                        : 0,
                    'avg_duration_days' => $this->averageTurnaroundTime($completed),
                    'avgDuration' => $this->averageTurnaroundTime($completed),
                    'most_common_type' => $caseTypeCounts->sortDesc()->keys()->first() ?? 'N/A',
                    'mostCommonCaseType' => $caseTypeCounts->sortDesc()->keys()->first() ?? 'N/A',
                    'total_revenue' => round($group->sum(fn (CaseModel $case) => $this->casePrice($case, $servicePrices)), 2),
                    'totalRevenue' => round($group->sum(fn (CaseModel $case) => $this->casePrice($case, $servicePrices)), 2),
                ];
            })
            ->values();

        $sortBy = request()->query('sort_by', 'total_cases');
        $direction = request()->query('sort_direction', 'desc');
        $map = [
            'clinic' => 'clinic',
            'doctor' => 'doctor',
            'total_cases' => 'total_cases',
            'completed' => 'completed',
            'on_time_percent' => 'on_time_percent',
            'avg_duration_days' => 'avg_duration_days',
            'most_common_type' => 'most_common_type',
            'total_revenue' => 'total_revenue',
        ];

        $key = $map[$sortBy] ?? 'total_cases';
        $rows = $direction === 'asc' ? $rows->sortBy($key) : $rows->sortByDesc($key);

        return $rows->values()->all();
    }

    private function detailedCaseRows(Collection $cases): array
    {
        return $cases->map(fn (CaseModel $case) => [
            'id' => $case->id,
            'case_id' => $case->case_number,
            'patient' => $case->patient?->user?->name,
            'lab' => $case->lab?->name,
            'due_date' => optional($case->due_date)?->toDateString(),
            'status' => $case->status,
            'actions' => ['view' => true],
        ])->values()->all();
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
