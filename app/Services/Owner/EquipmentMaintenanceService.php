<?php

namespace App\Services\Owner;

use App\Models\AiAlert;
use App\Models\MaintenanceCompany;
use App\Models\OwnerMaintenanceRequest;
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
            'requestId' => $request->request_code,
            'clinicId' => $request->clinic_id,
            'clinicName' => $request->clinic?->name,
            'equipment' => $request->equipment,
            'issueDescription' => $request->issue_description,
            'assignedCompanyId' => $request->assigned_company_id,
            'status' => $request->status,
            'dateCreated' => optional($request->created_at)->toISOString(),
            'lastUpdate' => optional($request->updated_at)->toISOString(),
        ])->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'Maintenance requests fetched successfully');
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
                'status' => $data['status'] ?? OwnerMaintenanceRequest::STATUS_OPEN,
                'created_by' => auth()->id(),
            ]);

            $request->loadMissing('clinic:id,name');

            return ServiceResult::success([
                'id' => $request->id,
                'requestId' => $request->request_code,
                'clinicId' => $request->clinic_id,
                'clinicName' => $request->clinic?->name,
                'equipment' => $request->equipment,
                'issueDescription' => $request->issue_description,
                'assignedCompanyId' => $request->assigned_company_id,
                'status' => $request->status,
                'dateCreated' => optional($request->created_at)->toISOString(),
                'lastUpdate' => optional($request->updated_at)->toISOString(),
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
            'requestId' => $updated->request_code,
            'clinicId' => $updated->clinic_id,
            'clinicName' => $updated->clinic?->name,
            'equipment' => $updated->equipment,
            'issueDescription' => $updated->issue_description,
            'assignedCompanyId' => $updated->assigned_company_id,
            'status' => $updated->status,
            'dateCreated' => optional($updated->created_at)->toISOString(),
            'lastUpdate' => optional($updated->updated_at)->toISOString(),
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
                'name' => $company->name,
                'contactPerson' => $company->contact_person,
                'phone' => $company->phone,
                'email' => $company->email,
                'totalRequests' => $totalRequests,
                'completionRate' => $totalRequests > 0
                    ? round(($completedRequests / $totalRequests) * 100, 2)
                    : 0,
                'avgResponseTime' => isset($avgResponseMinutesByCompany[$company->id])
                    ? round((float) $avgResponseMinutesByCompany[$company->id], 2)
                    : 0,
                'aiRating' => $company->ai_rating !== null ? (float) $company->ai_rating : null,
                'status' => $company->status,
                'logoUrl' => $company->logo_url ? asset($company->logo_url) : null,
                'feedback' => $company->feedback ?? [],
                'reports' => $company->reports ?? [],
            ];
        })->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ], 'Maintenance companies fetched successfully');
    }

    public function createCompany(array $data): array
    {
        $company = $this->maintenanceCompanyRepository->create($data);

        return ServiceResult::success([
            'id' => $company->id,
            'name' => $company->name,
            'contactPerson' => $company->contact_person,
            'phone' => $company->phone,
            'email' => $company->email,
            'totalRequests' => 0,
            'completionRate' => 0,
            'avgResponseTime' => 0,
            'aiRating' => $company->ai_rating !== null ? (float) $company->ai_rating : null,
            'status' => $company->status,
            'logoUrl' => $company->logo_url ? asset($company->logo_url) : null,
            'feedback' => $company->feedback ?? [],
            'reports' => $company->reports ?? [],
        ], 'Maintenance company created successfully', 201);
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
