<?php

namespace App\Services\Owner;

use App\Models\AiAlert;
use App\Models\MaintenanceCompany;
use App\Models\OwnerMaintenanceRequest;
use App\Models\Clinic;
use App\Repositories\AiAlertRepository;
use App\Repositories\MaintenanceCompanyRepository;
use App\Repositories\OwnerMaintenanceRequestRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentMaintenanceService
{
    public function __construct(
        private OwnerMaintenanceRequestRepository $maintenanceRequestRepository,
        private MaintenanceCompanyRepository $maintenanceCompanyRepository,
        private AiAlertRepository $aiAlertRepository,
    ) {}

    public function listRequests(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $requests = $this->maintenanceRequestRepository->paginate($filters, $perPage);

       $items = collect($requests->items())->map(fn (OwnerMaintenanceRequest $request) => [
    'id' => $request->id,
    'status' => $request->status,
    'created' => optional($request->created_at)->format('d/m/Y'),
    'assigned_company' => $request->company?->name ?? 'Unassigned',
    'equipment' => $request->equipment,
    'clinic' => $request->clinic?->name,
    'request_id' => $request->request_code,
])->all();

        return ServiceResult::success([
            'smart_alerts' => $this->smartAlerts(),
            'items' => $items,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => $this->maintenanceOptions(),
        ], 'Maintenance requests fetched successfully');
    }

    public function showRequest(int $id): array
    {
        $request = $this->maintenanceRequestRepository->findById($id);

        if (! $request) {
            return ServiceResult::error('Maintenance request not found', null, null, 404);
        }

        return ServiceResult::success([
            'request_id' => $request->request_code,
            'clinic' => $request->clinic?->name,
            'equipment' => $request->equipment,
            'issue' => $request->issue_description,
            'assigned_company' => [
                'id' => $request->assigned_company_id,
                'name' => $request->company?->name ?? 'Unassigned',
            ],
            'status' => $request->status,
            'editable_fields' => ['status', 'assigned_company_id'],
            'options' => $this->maintenanceOptions(),
        ], 'Maintenance request details fetched successfully');
    }

    public function createRequest(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $request = $this->maintenanceRequestRepository->create([
                'request_code' => $this->generateRequestCode(),
                'clinic_id' => $data['clinic_id'] ?? null,
                'equipment' => $data['equipment'],
                'issue_description' => $data['issue_description'],
                'assigned_company_id' => $data['assigned_company_id'] ?? null,
                'status' => $data['status'] ?? OwnerMaintenanceRequest::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            $request->loadMissing('clinic:id,name');

            return ServiceResult::success([
            'id' => $request->id,
            'request_id' => $request->request_code,
            'clinicId' => $request->clinic_id,
            'clinic' => $request->clinic?->name,
            'equipment' => $request->equipment,
            'issue' => $request->issue_description,
            'assigned_company_id' => $request->assigned_company_id,
            'status' => $request->status,
            'created' => optional($request->created_at)->format('d/m/Y'),
        ], 'Maintenance request created successfully', 201);
        });
    }

    public function updateRequest(int $id, array $data): array
    {
        $request = $this->maintenanceRequestRepository->findById($id);

        if (! $request) {
            return ServiceResult::error('Maintenance request not found', null, null, 404);
        }

        $updated = $this->maintenanceRequestRepository->update($request, $data);
        $updated->loadMissing('clinic:id,name');

        return ServiceResult::success([
            'id' => $updated->id,
            'request_id' => $updated->request_code,
            'clinic' => $updated->clinic?->name,
            'equipment' => $updated->equipment,
            'issue' => $updated->issue_description,
            'assigned_company_id' => $updated->assigned_company_id,
            'status' => $updated->status,
        ], 'Maintenance request updated successfully');
    }

    public function listCompanies(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $companies = $this->maintenanceCompanyRepository->paginate($filters, $perPage);

        $avgResponseMinutesByCompany = OwnerMaintenanceRequest::query()
            ->select(['assigned_company_id', 'created_at', 'updated_at'])
            ->whereNotNull('assigned_company_id')
            ->get()
            ->groupBy('assigned_company_id')
            ->map(function ($rows) {
                $avg = $rows->avg(fn ($row) => optional($row->created_at)?->diffInMinutes($row->updated_at) ?? 0);

                return round((float) $avg, 2);
            });

        $items = collect($companies->items())->map(function (MaintenanceCompany $company) use ($avgResponseMinutesByCompany) {
            $totalRequests = (int) ($company->total_requests ?? 0);
            $completedRequests = (int) ($company->completed_requests ?? 0);

            return [
                'id' => $company->id,
                'company' => $company->name,
                'contact' => $company->contact_person,
                'total_requests' => $totalRequests,
                'completion_rate' => $totalRequests > 0
                    ? round(($completedRequests / $totalRequests) * 100, 2)
                    : 0,
                'ai_rating' => $company->ai_rating !== null ? (float) $company->ai_rating : null,
                'status' => $company->status,
                'logoUrl' => $company->logo_url ? asset($company->logo_url) : null,
            ];
        })->all();

        return ServiceResult::success([
            'smart_alerts' => $this->smartAlerts(),
            'items' => $items,
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
            'filters' => [
                'statuses' => ['All Statuses', MaintenanceCompany::STATUS_ACTIVE, MaintenanceCompany::STATUS_INACTIVE],
            ],
        ], 'Maintenance companies fetched successfully');
    }

    public function showCompany(int $id): array
    {
        $company = $this->maintenanceCompanyRepository->findById($id);

        if (! $company) {
            return ServiceResult::error('Maintenance company not found', null, null, 404);
        }

        $totalRequests = (int) ($company->total_requests ?? 0);
        $completedRequests = (int) ($company->completed_requests ?? 0);
        $avgResponseMinutes = OwnerMaintenanceRequest::query()
            ->where('assigned_company_id', $company->id)
            ->get(['created_at', 'updated_at'])
            ->avg(fn ($row) => optional($row->created_at)?->diffInMinutes($row->updated_at) ?? 0);

        return ServiceResult::success([
            'id' => $company->id,
            'company' => $company->name,
            'contact_person' => $company->contact_person,
            'phone' => $company->phone,
            'email' => $company->email,
            'ai_rating' => $company->ai_rating !== null ? (float) $company->ai_rating : null,
            'performance' => [
                'completion_percent' => $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 2) : 0,
                'total_jobs' => $totalRequests,
            ],
            'avg_response_time' => [
                'hours' => round(((float) $avgResponseMinutes) / 60, 2),
            ],
            'recent_feedback' => collect($company->feedback ?? [])
                ->take(5)
                ->map(fn ($feedback) => [
                    'rating' => $feedback['rating'] ?? null,
                    'clinic' => $feedback['clinic'] ?? $feedback['clinic_name'] ?? null,
                    'comment' => $feedback['comment'] ?? null,
                ])
                ->values()
                ->all(),
            'status' => $company->status,
            'editable_fields' => ['status'],
        ], 'Maintenance company details fetched successfully');
    }

    public function createCompany(array $data): array
    {
        $company = $this->maintenanceCompanyRepository->create($data);

        return ServiceResult::success([
            'id' => $company->id,
            'company' => $company->name,
            'contact' => $company->contact_person,
            'phone' => $company->phone,
            'email' => $company->email,
            'total_requests' => 0,
            'completion_rate' => 0,
            'ai_rating' => $company->ai_rating !== null ? (float) $company->ai_rating : null,
            'status' => $company->status,
        ], 'Maintenance company created successfully', 201);
    }

    public function updateCompanyStatus(int $id, string $status): array
    {
        $company = $this->maintenanceCompanyRepository->findById($id);

        if (! $company) {
            return ServiceResult::error('Maintenance company not found', null, null, 404);
        }

        $updated = $this->maintenanceCompanyRepository->update($company, ['status' => $status]);

        return ServiceResult::success([
            'id' => $updated->id,
            'company' => $updated->name,
            'status' => $updated->status,
        ], 'Maintenance company status updated successfully');
    }

    public function reviewAlert(int $id): array
    {
        $alert = $this->aiAlertRepository->findById($id);

        if (! $alert) {
            return ServiceResult::error('AI alert not found', null, null, 404);
        }

        $updated = $this->aiAlertRepository->update($alert, [
            'is_reviewed' => true,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $updated->loadMissing('company:id,name');

        return ServiceResult::success($this->mapAlert($updated), 'AI alert reviewed successfully');
    }

    private function generateRequestCode(): string
    {
        return 'MR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }

    public function smartAlerts(): array
    {
        return AiAlert::query()
            ->where('is_reviewed', false)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AiAlert $alert) => $this->mapAlert($alert))
            ->values()
            ->all();
    }

    private function maintenanceOptions(): array
    {
        return [
            'statuses' => OwnerMaintenanceRequest::STATUSES,
            'assigned_companies' => collect([['id' => null, 'name' => 'Unassigned']])
                ->concat(MaintenanceCompany::query()->orderBy('name')->get(['id', 'name'])->map(fn ($company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                ]))
                ->values()
                ->all(),
        ];
    }

    private function mapAlert(AiAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
            'message' => $alert->message,
            'severity' => $alert->severity,
            'companyId' => $alert->company_id,
            'timestamp' => optional($alert->created_at)->toISOString(),
            'isReviewed' => (bool) $alert->is_reviewed,
        ];
    }
}
