<?php

namespace App\Services\Clinic\Insurance;

use App\DTOs\Clinic\Insurance\InsuranceClaimData;
use App\Http\Resources\Clinic\Insurance\InsuranceClaimResource;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\ClinicAppointment;
use App\Models\Clinic;
use App\Models\ClinicInvoice;
use App\Models\Patient;
use App\Repositories\Clinic\Insurance\InsuranceClaimRepository;
use App\Repositories\Clinic\Insurance\InsuranceCompanyRepository;
use App\Support\Clinic\BranchFilter;
use App\Support\ServiceResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InsuranceClaimService
{
    use BranchFilter;

    public function __construct(
        private InsuranceClaimRepository $repository,
        private InsuranceCompanyRepository $companyRepository,
    ) {
    }

    public function index(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $claims = $this->repository->listForClinic($clinicId, array_filter($filters, fn ($value) => $value !== null && $value !== ''));

        return ServiceResult::success(
            InsuranceClaimResource::collection($claims)->resolve(),
            'Insurance claims fetched successfully'
        );
    }

    public function show(int $claimId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $claim = $this->repository->findForClinic($clinicId, $claimId);
        if (! $claim) {
            return ServiceResult::error('Insurance claim not found.', null, null, 404);
        }

        return ServiceResult::success(
            (new InsuranceClaimResource($claim))->resolve(),
            'Insurance claim fetched successfully'
        );
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $dto = InsuranceClaimData::fromArray($data);
        $validation = $this->validateRelatedModels($clinicId, $dto);
        if ($validation !== null) {
            return $validation;
        }

        // Validate claim items if provided
        $itemsValidation = $this->validateClaimItems($clinicId, $dto->insuranceCompanyId, $data['items'] ?? []);
        if ($itemsValidation !== null) {
            return $itemsValidation;
        }

        $claim = DB::transaction(function () use ($clinicId, $dto, $data) {
            // Calculate gross_amount from items if provided
            $grossAmount = $dto->grossAmount;
            $itemsData = $data['items'] ?? [];
            if (!empty($itemsData)) {
                $grossAmount = 0;
                foreach ($itemsData as $item) {
                    $itemTotal = ((float) ($item['unit_price'] ?? 0)) * (int) ($item['quantity'] ?? 1);
                    $grossAmount += $itemTotal;
                }
                $grossAmount = round($grossAmount, 2);
            }

            $status = $dto->status ?? InsuranceClaim::STATUS_DRAFT;
            $approvedAmount = $dto->approvedAmount;
            if ($status === InsuranceClaim::STATUS_APPROVED && $approvedAmount === null) {
                $approvedAmount = round(($grossAmount * $dto->coveragePercentage) / 100, 2);
            }
            $amounts = $this->calculateAmounts($grossAmount, $dto->coveragePercentage, $approvedAmount, $dto->paidAmount);

            $claim = $this->repository->create([
                'clinic_id' => $clinicId,
                'insurance_company_id' => $dto->insuranceCompanyId,
                'patient_id' => $dto->patientId,
                'appointment_id' => $dto->appointmentId,
                'clinic_invoice_id' => $dto->clinicInvoiceId,
                'claim_number' => $this->generateClaimNumber(),
                'title' => $dto->title,
                'description' => $dto->description,
                'service_date' => $dto->serviceDate,
                'coverage_percentage' => $dto->coveragePercentage,
                'gross_amount' => $grossAmount,
                'patient_share_amount' => $amounts['patient_share_amount'],
                'insurance_share_amount' => $amounts['insurance_share_amount'],
                'approved_amount' => $amounts['approved_amount'],
                'paid_amount' => $amounts['paid_amount'],
                'status' => $status,
                'notes' => $dto->notes,
                'status_notes' => $dto->statusNotes,
                'patient_consent_required' => $data['patient_consent_required'] ?? false,
                'submitted_at' => $status === InsuranceClaim::STATUS_SUBMITTED ? now() : null,
                'reviewed_at' => in_array($status, [
                    InsuranceClaim::STATUS_APPROVED,
                    InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                    InsuranceClaim::STATUS_REJECTED,
                    InsuranceClaim::STATUS_PAID,
                ], true) ? now() : null,
                'settled_at' => $status === InsuranceClaim::STATUS_PAID ? now() : null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Create claim items if provided
            if (!empty($itemsData)) {
                $this->createItems($claim, $itemsData);
            }

            return $claim;
        });

        return $this->show($claim->id);
    }

    public function update(int $claimId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $claim = $this->repository->findForClinic($clinicId, $claimId);
        if (! $claim) {
            return ServiceResult::error('Insurance claim not found.', null, null, 404);
        }

        $validation = $this->validatePartialRelatedModels($clinicId, $data, $claim);
        if ($validation !== null) {
            return $validation;
        }

        // Validate claim items if provided
        $itemsValidation = $this->validateClaimItems($clinicId, $claim->insurance_company_id, $data['items'] ?? []);
        if ($itemsValidation !== null) {
            return $itemsValidation;
        }

        $nextStatus = $data['status'] ?? $claim->status;

        $previousStatus = $claim->status;
        $updatedClaim = DB::transaction(function () use ($claim, $data, $nextStatus, $clinicId) {
            $grossAmount = isset($data['gross_amount']) ? (float) $data['gross_amount'] : (float) $claim->gross_amount;
            $itemsData = $data['items'] ?? [];

            // Recalculate gross_amount from items if provided
            if (!empty($itemsData)) {
                $grossAmount = 0;
                foreach ($itemsData as $item) {
                    $itemTotal = ((float) ($item['unit_price'] ?? 0)) * (int) ($item['quantity'] ?? 1);
                    $grossAmount += $itemTotal;
                }
                $grossAmount = round($grossAmount, 2);
            }

            $coveragePercentage = isset($data['coverage_percentage']) ? (float) $data['coverage_percentage'] : (float) $claim->coverage_percentage;
            $approvedAmount = array_key_exists('approved_amount', $data) ? (($data['approved_amount'] === null) ? null : (float) $data['approved_amount']) : (float) $claim->approved_amount;
            $paidAmount = array_key_exists('paid_amount', $data) ? (($data['paid_amount'] === null) ? null : (float) $data['paid_amount']) : (float) $claim->paid_amount;

            if ($nextStatus === InsuranceClaim::STATUS_APPROVED && ! array_key_exists('approved_amount', $data)) {
                $approvedAmount = round(($grossAmount * $coveragePercentage) / 100, 2);
            }

            $amounts = $this->calculateAmounts($grossAmount, $coveragePercentage, $approvedAmount, $paidAmount);

            $attributes = array_filter($data, fn ($key) => in_array($key, $this->persistedClaimKeys(), true), ARRAY_FILTER_USE_KEY);
            $attributes = array_merge($attributes, [
                'gross_amount' => $grossAmount,
                'coverage_percentage' => $coveragePercentage,
                'patient_share_amount' => $amounts['patient_share_amount'],
                'insurance_share_amount' => $amounts['insurance_share_amount'],
                'approved_amount' => $amounts['approved_amount'],
                'paid_amount' => $amounts['paid_amount'],
                'updated_by' => auth()->id(),
            ]);

            if ($nextStatus !== $claim->status) {
                if ($nextStatus === InsuranceClaim::STATUS_SUBMITTED && $claim->submitted_at === null) {
                    $attributes['submitted_at'] = now();
                }

                if (in_array($nextStatus, [
                    InsuranceClaim::STATUS_APPROVED,
                    InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                    InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
                    InsuranceClaim::STATUS_REJECTED,
                    InsuranceClaim::STATUS_PAID,
                ], true)) {
                    $attributes['reviewed_at'] = now();
                }

                if ($nextStatus === InsuranceClaim::STATUS_PAID) {
                    $attributes['settled_at'] = now();
                    if (! array_key_exists('paid_amount', $data)) {
                        $attributes['paid_amount'] = $amounts['approved_amount'];
                    }
                }
            }

            $updatedClaim = $this->repository->update($claim, $attributes);

            // Update claim items if provided
            if (!empty($itemsData)) {
                $updatedClaim->items()->delete();
                $this->createItems($updatedClaim, $itemsData);
                $updatedClaim = $updatedClaim->fresh();
            }

            return $updatedClaim;
        });

        // Trigger WhatsApp notification on status change (non-blocking)
        if ($nextStatus !== $previousStatus) {
            $this->triggerStatusNotification($updatedClaim, $previousStatus, $nextStatus);
        }

        return ServiceResult::success(
            (new InsuranceClaimResource($updatedClaim))->resolve(),
            'Insurance claim updated successfully'
        );
    }

  public function updateStatus(int $claimId, array $data): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $claim = $this->repository->findForClinic($clinicId, $claimId);
    if (! $claim) {
        return ServiceResult::error('Insurance claim not found.', null, null, 404);
    }

    $status = $data['status'] === 'under_review'
        ? InsuranceClaim::STATUS_PARTIALLY_APPROVED
        : $data['status'];

    $attributes = [
        'status' => $status,
        'updated_by' => auth()->id(),
    ];

    if ($status === InsuranceClaim::STATUS_APPROVED) {
        $attributes['approved_amount'] = (float) $claim->insurance_share_amount;
    }

    if ($status === InsuranceClaim::STATUS_APPROVED_WITH_LIMIT) {
        $attributes['approved_amount'] = round(min((float) ($data['approved_amount'] ?? 0), (float) $claim->insurance_share_amount), 2);
    }

    if ($status === InsuranceClaim::STATUS_SUBMITTED && $claim->submitted_at === null) {
        $attributes['submitted_at'] = now();
    }

    if (in_array($status, [
        InsuranceClaim::STATUS_APPROVED,
        InsuranceClaim::STATUS_PARTIALLY_APPROVED,
        InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
        InsuranceClaim::STATUS_REJECTED,
        InsuranceClaim::STATUS_PAID,
    ], true)) {
        $attributes['reviewed_at'] = now();
    }

    if ($status === InsuranceClaim::STATUS_PAID) {
        $attributes['settled_at'] = now();
        if ((float) $claim->paid_amount <= 0) {
            $attributes['paid_amount'] = (float) $claim->approved_amount;
        }
    }

    $previousStatus = $claim->status;
    $updatedClaim = $this->repository->update($claim, $attributes);

    if ($status !== $previousStatus) {
        $this->triggerStatusNotification($updatedClaim, $previousStatus, $status);
    }

    return ServiceResult::success(
        (new InsuranceClaimResource($updatedClaim))->resolve(),
        'Insurance claim status updated successfully'
    );
}

    public function managementCards(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $anchorDate = InsuranceClaim::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('service_date', '<=', now()->toDateString())
            ->max('service_date');

        $anchor = $anchorDate ? Carbon::parse($anchorDate) : now();
        $currentStart = $anchor->copy()->startOfMonth();
        $currentEnd = $anchor->copy()->endOfMonth();
        $previousStart = $anchor->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $anchor->copy()->subMonthNoOverflow()->endOfMonth();

        $currentClaims = $this->claimsForCards($clinicId, $currentStart, $currentEnd, $filters);
        $previousClaims = $this->claimsForCards($clinicId, $previousStart, $previousEnd, $filters);

        $currentClaimed = round((float) $currentClaims->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2);
        $previousClaimed = round((float) $previousClaims->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2);
        $currentApprovalRate = $this->approvalRate($currentClaims);
        $previousApprovalRate = $this->approvalRate($previousClaims);
        $currentAvgTime = $this->averageClaimTime($currentClaims);
        $previousAvgTime = $this->averageClaimTime($previousClaims);

        return ServiceResult::success([
            'cards' => [
                [
                    'key' => 'total_amount_claimed',
                    'label' => 'Total Amount Claimed',
                    'value' => $currentClaimed,
                    ...$this->changeMeta($currentClaimed, $previousClaimed, true),
                ],
                [
                    'key' => 'approval_rate',
                    'label' => 'Approval Rate',
                    'value' => $currentApprovalRate,
                    ...$this->changeMeta($currentApprovalRate, $previousApprovalRate, true),
                ],
                [
                    'key' => 'avg_claim_time',
                    'label' => 'Avg. Claim Time',
                    'value' => $currentAvgTime,
                    ...$this->changeMeta($currentAvgTime, $previousAvgTime, false),
                ],
            ],
        ], 'Insurance management cards fetched successfully');
    }

    public function destroy(int $claimId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $claim = $this->repository->findForClinic($clinicId, $claimId);
        if (! $claim) {
            return ServiceResult::error('Insurance claim not found.', null, null, 404);
        }

        $this->repository->delete($claim);

        return ServiceResult::success(null, 'Insurance claim deleted successfully');
    }

    public function analytics(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        [$from, $to] = $this->dateRangeBounds($filters['date_range'] ?? 'Last 90 Days');

        $claims = InsuranceClaim::query()
            ->with('company:id,name')
            ->where('clinic_id', $clinicId)
            ->when($this->selectedBranchId($filters), fn (Builder $query, int $branchId) => $query->whereHas('appointment', fn (Builder $appointment) => $appointment->where('branch_id', $branchId)))
            ->whereIn('status', InsuranceClaim::reportStatuses())
            ->when($from, fn (Builder $query) => $query->whereDate('service_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('service_date', '<=', $to))
            ->when($filters['insurance_company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereIn('status', $this->storedStatusesForFilter($status)))
            ->get();

        $statusDistribution = collect(InsuranceClaim::reportStatuses())->map(function (string $status) use ($claims) {
            $count = $claims->whereIn('status', $this->storedStatusesForFilter($status))->count();
            $total = max($claims->count(), 1);

            return [
                'status' => $status,
                'label' => $this->statusLabel($status),
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 2),
            ];
        })->values();

        $approvedStatuses = [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
            InsuranceClaim::STATUS_PAID,
        ];
        $approvedCount = $claims->whereIn('status', $approvedStatuses)->count();
        $submittedCount = max($claims->whereIn('status', [
            InsuranceClaim::STATUS_SUBMITTED,
            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
            InsuranceClaim::STATUS_PAID,
            InsuranceClaim::STATUS_REJECTED,
        ])->count(), 1);

        return ServiceResult::success([
            'filters' => [
                'date_range' => $filters['date_range'] ?? 'Last 90 Days',
                'insurance_company_id' => $filters['insurance_company_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $from,
                'date_to' => $to,
            ],
            'cards' => [
                'total_amount_claimed' => round((float) $claims->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2),
                'approval_rate' => round(($approvedCount / $submittedCount) * 100, 2),
                'avg_claim_time_days' => round((float) $claims
                    ->filter(fn (InsuranceClaim $claim) => $claim->submitted_at && $claim->reviewed_at)
                    ->avg(fn (InsuranceClaim $claim) => $claim->submitted_at->diffInDays($claim->reviewed_at)), 1),
            ],
            'claim_status_distribution' => $statusDistribution,
            'top_companies_by_claim_amount' => $claims
                ->groupBy('insurance_company_id')
                ->map(function ($group) {
                    $company = $group->first()?->company;

                    return [
                        'insurance_company_id' => $company?->id,
                        'name' => $company?->name,
                        'claims_count' => $group->count(),
                        'claimed_amount' => round((float) $group->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2),
                    ];
                })
                ->sortByDesc('claimed_amount')
                ->values()
                ->take(5)
                ->all(),
            'monthly_billed_vs_paid' => $this->monthlyBilledPaidSeries($claims, $from, $to),
            'totals_by_status' => $statusDistribution->pluck('count', 'status'),
            'amounts' => [
                'claimed_amount' => round((float) $claims->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2),
                'approved_amount' => round((float) $claims->whereIn('status', $approvedStatuses)->sum(fn (InsuranceClaim $claim) => $this->effectiveApprovedAmount($claim)), 2),
                'paid_amount' => round((float) $claims->sum('paid_amount'), 2),
            ],
        ], 'Insurance analytics fetched successfully');
    }

    public function monthly(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $year = (int) ($filters['year'] ?? now()->format('Y'));

        $claims = InsuranceClaim::query()
            ->where('clinic_id', $clinicId)
            ->whereYear('service_date', $year)
            ->when($this->selectedBranchId($filters), fn (Builder $query, int $branchId) => $query->whereHas('appointment', fn (Builder $appointment) => $appointment->where('branch_id', $branchId)))
            ->when($filters['insurance_company_id'] ?? null, fn ($query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->get();

        $series = collect(range(1, 12))->map(function (int $month) use ($claims, $year) {
            $monthClaims = $claims->filter(fn (InsuranceClaim $claim) => (int) $claim->service_date->format('n') === $month);

            return [
                'month' => sprintf('%d-%02d', $year, $month),
                'claims_count' => $monthClaims->count(),
                'gross_amount' => round((float) $monthClaims->sum('gross_amount'), 2),
                'approved_amount' => round((float) $monthClaims->sum('approved_amount'), 2),
                'paid_amount' => round((float) $monthClaims->sum('paid_amount'), 2),
            ];
        })->all();

        return ServiceResult::success(['year' => $year, 'series' => $series], 'Monthly insurance report fetched successfully');
    }

    public function approvalReport(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $from = $filters['date_from'] ?? now()->subMonths(3)->toDateString();
        $to = $filters['date_to'] ?? now()->toDateString();

        $claims = InsuranceClaim::query()
            ->with(['company:id,name', 'patient.user:id,name', 'appointment.doctor:id,name', 'invoice.doctor:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereBetween('service_date', [$from, $to])
            ->when($filters['insurance_company_id'] ?? null, fn ($query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->when($filters['doctor_id'] ?? null, fn ($query, int $doctorId) => $query->whereHas('appointment', fn ($appointment) => $appointment->where('doctor_user_id', $doctorId)))
            ->when($this->selectedBranchId($filters), fn ($query, int $branchId) => $query->whereHas('appointment', fn ($appointment) => $appointment->where('branch_id', $branchId)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereIn('status', $this->storedStatusesForFilter($status)))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($items) => $items->where('service_name', 'like', "%{$search}%"))
                        ->orWhereHas('patient.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->get();

        $approvedStatuses = [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
            InsuranceClaim::STATUS_PAID,
        ];
        $pendingStatuses = [InsuranceClaim::STATUS_SUBMITTED, InsuranceClaim::STATUS_PARTIALLY_APPROVED];
        $rejectedStatuses = [InsuranceClaim::STATUS_REJECTED];
        $rows = $this->approvalReportRows($claims);
        $downloadUrls = $this->approvalReportDownloadUrls($clinicId, $filters + ['date_from' => $from, 'date_to' => $to]);

        return ServiceResult::success([
            'date_from' => $from,
            'date_to' => $to,
            'filters' => $filters,
            'cards' => [
                'total_approvals' => $claims->whereIn('status', $approvedStatuses)->count(),
                'total_approved_amount' => round((float) $claims->whereIn('status', $approvedStatuses)->sum(fn (InsuranceClaim $claim) => $this->effectiveApprovedAmount($claim)), 2),
                'pending' => $claims->whereIn('status', $pendingStatuses)->count(),
                'rejected' => $claims->whereIn('status', $rejectedStatuses)->count(),
            ],
            'status_filters' => $this->statusOptions(),
            'table' => $rows,
            'pdf_url' => $downloadUrls['pdf_url'],
            'excel_url' => $downloadUrls['excel_url'],
            'print_url' => $downloadUrls['pdf_url'],
        ], 'Insurance approval report fetched successfully');
    }

    public function approvalReportDownload(int $clinicId, array $filters, string $format): array
    {
        if (! Clinic::query()->whereKey($clinicId)->exists()) {
            return ServiceResult::error('Clinic was not found.', null, null, 404);
        }

        $from = $filters['date_from'] ?? now()->subMonths(3)->toDateString();
        $to = $filters['date_to'] ?? now()->toDateString();

        $claims = InsuranceClaim::query()
            ->with(['company:id,name', 'patient.user:id,name', 'appointment.doctor:id,name', 'invoice.doctor:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereBetween('service_date', [$from, $to])
            ->when($filters['insurance_company_id'] ?? null, fn ($query, $companyId) => $query->where('insurance_company_id', (int) $companyId))
            ->when($filters['doctor_id'] ?? null, fn ($query, $doctorId) => $query->whereHas('appointment', fn ($appointment) => $appointment->where('doctor_user_id', (int) $doctorId)))
            ->when($this->selectedBranchId($filters), fn ($query, $branchId) => $query->whereHas('appointment', fn ($appointment) => $appointment->where('branch_id', (int) $branchId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereIn('status', $this->storedStatusesForFilter((string) $status)))
            ->get();

        $rows = $this->approvalReportRows($claims);

        if ($format === 'excel') {
            return ServiceResult::success([
                'filename' => 'monthly-insurance-approvals-' . now()->format('YmdHis') . '.xls',
                'content_type' => 'application/vnd.ms-excel; charset=UTF-8',
                'content' => $this->renderApprovalReportCsv($rows),
            ], 'Insurance approval Excel generated successfully');
        }

        return ServiceResult::success([
            'filename' => 'monthly-insurance-approvals-' . now()->format('YmdHis') . '.pdf',
            'content_type' => 'application/pdf',
            'content' => Pdf::loadHTML($this->renderApprovalReportHtml($rows, $from, $to))->output(),
        ], 'Insurance approval PDF generated successfully');
    }

    private function dateRangeBounds(string $dateRange): array
    {
        return match ($dateRange) {
            'Last 30 Days' => [now()->subDays(30)->toDateString(), now()->toDateString()],
            'Last Year' => [now()->subYear()->toDateString(), now()->toDateString()],
            'All' => [null, null],
            default => [now()->subDays(90)->toDateString(), now()->toDateString()],
        };
    }

    private function storedStatusesForFilter(string $status): array
    {
        return match ($status) {
            'under_review' => [InsuranceClaim::STATUS_PARTIALLY_APPROVED],
            default => [$status],
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            InsuranceClaim::STATUS_SUBMITTED => 'Submitted',
            'under_review', InsuranceClaim::STATUS_PARTIALLY_APPROVED => 'Under Review',
            InsuranceClaim::STATUS_APPROVED => 'Approved',
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT => 'Approved with Limit',
            InsuranceClaim::STATUS_PAID => 'Paid',
            InsuranceClaim::STATUS_REJECTED => 'Rejected',
            default => Str::of($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusOptions(): array
    {
        return collect(InsuranceClaim::reportStatuses())
            ->map(fn (string $status) => [
                'id' => $status,
                'value' => $status,
                'label' => $this->statusLabel($status),
            ])
            ->prepend(['id' => 'all', 'value' => null, 'label' => 'All'])
            ->values()
            ->all();
    }

    private function claimsForCards(int $clinicId, Carbon $from, Carbon $to, array $filters)
    {
        return InsuranceClaim::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->selectedBranchId($filters), fn (Builder $query, int $branchId) => $query->whereHas('appointment', fn (Builder $appointment) => $appointment->where('branch_id', $branchId)))
            ->when($filters['insurance_company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereIn('status', $this->storedStatusesForFilter($status)))
            ->get();
    }

    private function approvalRate($claims): float
    {
        $approvedStatuses = [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
            InsuranceClaim::STATUS_PAID,
        ];

        return round(($claims->whereIn('status', $approvedStatuses)->count() / max($claims->count(), 1)) * 100, 1);
    }

    private function averageClaimTime($claims): float
    {
        return round((float) $claims
            ->filter(fn (InsuranceClaim $claim) => $claim->submitted_at && $claim->reviewed_at)
            ->avg(fn (InsuranceClaim $claim) => $claim->submitted_at->diffInDays($claim->reviewed_at)), 1);
    }

    private function changeMeta(float $current, float $previous, bool $higherIsBetter): array
    {
        $change = $previous == 0.0
            ? ($current == 0.0 ? 0.0 : 100.0)
            : round((($current - $previous) / abs($previous)) * 100, 1);
        return [
            'previous_value' => $previous,
            'change_percentage' => $change,
            'change_direction' => $change >= 0 ? 'up' : 'down',
        ];
    }

    private function effectiveClaimAmount(InsuranceClaim $claim): float
    {
        if ($claim->status === InsuranceClaim::STATUS_APPROVED_WITH_LIMIT) {
            return (float) $claim->approved_amount;
        }

        return (float) $claim->gross_amount;
    }

    private function effectiveApprovedAmount(InsuranceClaim $claim): float
    {
        if ($claim->status === InsuranceClaim::STATUS_APPROVED_WITH_LIMIT) {
            return (float) $claim->approved_amount;
        }

        if (in_array($claim->status, [InsuranceClaim::STATUS_APPROVED, InsuranceClaim::STATUS_PAID], true)) {
            return (float) ($claim->approved_amount ?: $claim->insurance_share_amount);
        }

        return 0.0;
    }

    private function monthlyBilledPaidSeries($claims, ?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfMonth() : optional($claims->min('service_date'))?->copy()->startOfMonth();
        $end = $to ? Carbon::parse($to)->startOfMonth() : optional($claims->max('service_date'))?->copy()->startOfMonth();

        if (! $start || ! $end) {
            $start = now()->startOfMonth();
            $end = now()->startOfMonth();
        }

        $series = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $monthClaims = $claims->filter(fn (InsuranceClaim $claim) => $claim->service_date && $claim->service_date->isSameMonth($cursor));
            $series[] = [
                'month' => $cursor->format('M Y'),
                'month_key' => $cursor->format('Y-m'),
                'billed' => round((float) $monthClaims->sum(fn (InsuranceClaim $claim) => $this->effectiveClaimAmount($claim)), 2),
                'paid' => round((float) $monthClaims->sum('paid_amount'), 2),
            ];
        }

        return $series;
    }

    private function approvalReportRows($claims): array
    {
        return $claims->map(function (InsuranceClaim $claim) {
            $appointment = $claim->appointment;
            $patientName = $claim->patient?->user?->name ?? $appointment?->patient_name;
            $doctor = $appointment?->doctor?->name ?? $claim->invoice?->doctor?->name;

            return [
                'approval_id' => $claim->claim_number,
                'patient_name' => $patientName,
                'file_number' => $claim->patient?->patient_number,
                'company' => $claim->company?->name,
                'doctor' => $doctor,
                'branch' => $appointment?->branch,
                'service' => $claim->items->first()?->service_name ?? $appointment?->service_name ?? $claim->title,
                'amount' => $this->effectiveApprovedAmount($claim) ?: (float) $claim->gross_amount,
                'approval_date' => optional($claim->reviewed_at)?->format('d/m/Y') ?? 'N/A',
                'status' => $this->statusLabel($claim->status),
                'raw_status' => $claim->status,
            ];
        })->values()->all();
    }

    private function approvalReportDownloadUrls(int $clinicId, array $filters): array
    {
        $expiresAt = now()->addHours(12);
        $params = ['clinic_id' => $clinicId];

        foreach (['date_from', 'date_to', 'insurance_company_id', 'doctor_id', 'branch_id', 'status', 'search'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $params[$key] = $filters[$key];
            }
        }

        return [
            'pdf_url' => URL::temporarySignedRoute('clinic.insurance.approval-report.pdf', $expiresAt, $params),
            'excel_url' => URL::temporarySignedRoute('clinic.insurance.approval-report.excel', $expiresAt, $params),
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    private function renderApprovalReportCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Approval ID', 'Patient Name', 'File Number', 'Company', 'Doctor', 'Branch', 'Service', 'Amount', 'Approval Date', 'Status']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['approval_id'],
                $row['patient_name'],
                $row['file_number'],
                $row['company'],
                $row['doctor'],
                $row['branch'],
                $row['service'],
                $row['amount'],
                $row['approval_date'],
                $row['status'],
            ]);
        }
        rewind($handle);

        return "\xEF\xBB\xBF" . stream_get_contents($handle);
    }

    private function renderApprovalReportHtml(array $rows, string $from, string $to): string
    {
        $body = collect($rows)->map(fn (array $row) => '<tr><td>' . e($row['approval_id']) . '</td><td>' . e($row['patient_name']) . '</td><td>' . e($row['file_number']) . '</td><td>' . e($row['company']) . '</td><td>' . e($row['doctor']) . '</td><td>' . e($row['branch']) . '</td><td>' . e($row['service']) . '</td><td>' . number_format((float) $row['amount'], 2) . '</td><td>' . e($row['approval_date']) . '</td><td>' . e($row['status']) . '</td></tr>')->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#111827}table{width:100%;border-collapse:collapse}th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:11px}th{background:#f3f4f6}</style></head><body><h1>Monthly Insurance Approvals Report</h1><p>' . e($from) . ' to ' . e($to) . '</p><table><thead><tr><th>Approval ID</th><th>Patient Name</th><th>File Number</th><th>Company</th><th>Doctor</th><th>Branch</th><th>Service</th><th>Amount</th><th>Approval Date</th><th>Status</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';
    }

    private function validateRelatedModels(int $clinicId, InsuranceClaimData $dto): ?array
    {
        if (! $this->companyRepository->findForClinic($clinicId, $dto->insuranceCompanyId)) {
            return ServiceResult::error('Insurance company not found.', null, [
                'insurance_company_id' => ['Insurance company not found for this clinic.'],
            ], 422);
        }

        if (! Patient::query()->where('clinic_id', $clinicId)->find($dto->patientId)) {
            return ServiceResult::error('Patient not found.', null, [
                'patient_id' => ['Patient not found for this clinic.'],
            ], 422);
        }

        if ($dto->appointmentId && ! $this->branchContext()->applyToAppointments(ClinicAppointment::query()->where('clinic_id', $clinicId))->find($dto->appointmentId)) {
            return ServiceResult::error('Appointment not found.', null, [
                'appointment_id' => ['Appointment not found for this clinic.'],
            ], 422);
        }

        if ($dto->clinicInvoiceId && ! ClinicInvoice::query()->where('clinic_id', $clinicId)->find($dto->clinicInvoiceId)) {
            return ServiceResult::error('Invoice not found.', null, [
                'clinic_invoice_id' => ['Invoice not found for this clinic.'],
            ], 422);
        }

        if ($dto->clinicInvoiceId) {
            $invoice = ClinicInvoice::query()->where('clinic_id', $clinicId)->find($dto->clinicInvoiceId);
            if ($invoice && $invoice->patient_id && $invoice->patient_id !== $dto->patientId) {
                return ServiceResult::error('Invoice patient mismatch.', null, [
                    'clinic_invoice_id' => ['Selected invoice does not belong to the selected patient.'],
                ], 422);
            }
        }

        if ($dto->appointmentId) {
            $appointment = $this->branchContext()->applyToAppointments(ClinicAppointment::query()->where('clinic_id', $clinicId))->find($dto->appointmentId);
            if ($appointment && $appointment->patient_id && $appointment->patient_id !== $dto->patientId) {
                return ServiceResult::error('Appointment patient mismatch.', null, [
                    'appointment_id' => ['Selected appointment does not belong to the selected patient.'],
                ], 422);
            }
        }

        return null;
    }

    private function validatePartialRelatedModels(int $clinicId, array $data, InsuranceClaim $claim): ?array
    {
        if (isset($data['insurance_company_id']) && ! $this->companyRepository->findForClinic($clinicId, (int) $data['insurance_company_id'])) {
            return ServiceResult::error('Insurance company not found.', null, [
                'insurance_company_id' => ['Insurance company not found for this clinic.'],
            ], 422);
        }

        if (isset($data['patient_id']) && ! Patient::query()->where('clinic_id', $clinicId)->find((int) $data['patient_id'])) {
            return ServiceResult::error('Patient not found.', null, [
                'patient_id' => ['Patient not found for this clinic.'],
            ], 422);
        }

        if (isset($data['appointment_id']) && $data['appointment_id'] !== null
            && ! $this->branchContext()->applyToAppointments(ClinicAppointment::query()->where('clinic_id', $clinicId))->find((int) $data['appointment_id'])) {
            return ServiceResult::error('Appointment not found.', null, [
                'appointment_id' => ['Appointment not found for this clinic.'],
            ], 422);
        }

        if (isset($data['clinic_invoice_id']) && $data['clinic_invoice_id'] !== null
            && ! ClinicInvoice::query()->where('clinic_id', $clinicId)->find((int) $data['clinic_invoice_id'])) {
            return ServiceResult::error('Invoice not found.', null, [
                'clinic_invoice_id' => ['Invoice not found for this clinic.'],
            ], 422);
        }

        $patientId = isset($data['patient_id']) ? (int) $data['patient_id'] : $claim->patient_id;
        $invoiceId = array_key_exists('clinic_invoice_id', $data) ? $data['clinic_invoice_id'] : $claim->clinic_invoice_id;
        $appointmentId = array_key_exists('appointment_id', $data) ? $data['appointment_id'] : $claim->appointment_id;

        if ($invoiceId) {
            $invoice = ClinicInvoice::query()->where('clinic_id', $clinicId)->find((int) $invoiceId);
            if ($invoice && $invoice->patient_id && $invoice->patient_id !== $patientId) {
                return ServiceResult::error('Invoice patient mismatch.', null, [
                    'clinic_invoice_id' => ['Selected invoice does not belong to the selected patient.'],
                ], 422);
            }
        }

        if ($appointmentId) {
            $appointment = ClinicAppointment::query()->where('clinic_id', $clinicId)->find((int) $appointmentId);
            if ($appointment && $appointment->patient_id && $appointment->patient_id !== $patientId) {
                return ServiceResult::error('Appointment patient mismatch.', null, [
                    'appointment_id' => ['Selected appointment does not belong to the selected patient.'],
                ], 422);
            }
        }

        return null;
    }

    private function calculateAmounts(
        float $grossAmount,
        float $coveragePercentage,
        ?float $approvedAmount,
        ?float $paidAmount,
    ): array {
        $insuranceShare = round(($grossAmount * $coveragePercentage) / 100, 2);
        $patientShare = round(max($grossAmount - $insuranceShare, 0), 2);
        $resolvedApproved = $approvedAmount === null ? 0.0 : round(min($approvedAmount, $insuranceShare), 2);
        $resolvedPaid = $paidAmount === null ? 0.0 : round(min($paidAmount, $resolvedApproved), 2);

        return [
            'insurance_share_amount' => $insuranceShare,
            'patient_share_amount' => $patientShare,
            'approved_amount' => $resolvedApproved,
            'paid_amount' => $resolvedPaid,
        ];
    }

    private function isValidTransition(string $from, string $to): bool
    {
        $map = [
            InsuranceClaim::STATUS_DRAFT => [
                InsuranceClaim::STATUS_SUBMITTED,
                InsuranceClaim::STATUS_CANCELLED,
            ],
            InsuranceClaim::STATUS_SUBMITTED => [
                InsuranceClaim::STATUS_APPROVED,
                InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
                InsuranceClaim::STATUS_REJECTED,
                InsuranceClaim::STATUS_CANCELLED,
            ],
            InsuranceClaim::STATUS_APPROVED => [
                InsuranceClaim::STATUS_PAID,
            ],
            InsuranceClaim::STATUS_PARTIALLY_APPROVED => [
                InsuranceClaim::STATUS_PAID,
            ],
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT => [
                InsuranceClaim::STATUS_PAID,
            ],
            InsuranceClaim::STATUS_REJECTED => [],
            InsuranceClaim::STATUS_PAID => [],
            InsuranceClaim::STATUS_CANCELLED => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function generateClaimNumber(): string
    {
        do {
            $number = 'CLM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (InsuranceClaim::query()->where('claim_number', $number)->exists());

        return $number;
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function persistedClaimKeys(): array
    {
        return [
            'insurance_company_id',
            'patient_id',
            'appointment_id',
            'clinic_invoice_id',
            'title',
            'description',
            'service_date',
            'coverage_percentage',
            'gross_amount',
            'approved_amount',
            'paid_amount',
            'status',
            'notes',
            'status_notes',
            'patient_consent_required',
        ];
    }

    /**
     * Create claim items from request data
     */
    public function createItems(InsuranceClaim $claim, array $itemsData): void
    {
        if (empty($itemsData)) {
            return;
        }

        foreach ($itemsData as $itemData) {
            $totalAmount = isset($itemData['unit_price'], $itemData['quantity'])
                ? round((float) $itemData['unit_price'] * (int) $itemData['quantity'], 2)
                : 0;

            $claim->items()->create([
                'insurance_price_list_item_id' => $itemData['insurance_price_list_item_id'] ?? null,
                'service_id' => $itemData['service_id'] ?? null,
                'code' => $itemData['code'] ?? null,
                'service_name' => $itemData['service_name'] ?? '',
                'category_id' => $itemData['category_id'] ?? null,
                'category_name' => $itemData['category_name'] ?? null,
                'unit_price' => (float) ($itemData['unit_price'] ?? 0),
                'quantity' => (int) ($itemData['quantity'] ?? 1),
                'total_amount' => $totalAmount,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Update gross amount based on claim items
     */
    public function calculateGrossAmountFromItems(InsuranceClaim $claim): float
    {
        return $claim->items()->sum('total_amount') ?: (float) $claim->gross_amount;
    }

    /**
     * Send WhatsApp notification on status change (non-blocking)
     */
    public function triggerStatusNotification(InsuranceClaim $claim, string $previousStatus, string $newStatus): void
    {
        if (in_array($newStatus, [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
        ], true)) {
            try {
                $service = app(ClaimStatusWhatsAppNotificationService::class, ['claim' => $claim]);
                $service->sendNotification();
            } catch (Exception $e) {
                Log::error('Failed to send WhatsApp notification', [
                    'claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
                // Non-blocking - don't throw exception
            }
        }
    }

    /**
     * Validate that items belong to correct clinic and insurance company
     */
    public function validateClaimItems(int $clinicId, int $insuranceCompanyId, array $itemsData): ?array
    {
        if (empty($itemsData)) {
            return null;
        }

        foreach ($itemsData as $index => $itemData) {
            if (isset($itemData['insurance_price_list_item_id'])) {
                // Verify price list item exists and belongs to correct clinic/company
                $priceListItem = \App\Models\InsurancePriceListItem::find($itemData['insurance_price_list_item_id']);
                if (!$priceListItem) {
                    return ServiceResult::error('Price list item not found.', null, [
                        "items.{$index}.insurance_price_list_item_id" => ['Price list item not found.'],
                    ], 422);
                }

                // Verify price list belongs to the insurance company
                $priceList = $priceListItem->priceList ?? $priceListItem->list;
                if ($priceList && $priceList->insurance_company_id !== $insuranceCompanyId) {
                    return ServiceResult::error('Price list item does not belong to selected insurance company.', null, [
                        "items.{$index}.insurance_price_list_item_id" => ['Price list item does not belong to selected insurance company.'],
                    ], 422);
                }

                // Verify clinic_id match if available
                if ($priceList && $priceList->clinic_id && $priceList->clinic_id !== $clinicId) {
                    return ServiceResult::error('Price list item does not belong to your clinic.', null, [
                        "items.{$index}.insurance_price_list_item_id" => ['Price list item does not belong to your clinic.'],
                    ], 422);
                }
            }
        }

        return null;
    }
}
