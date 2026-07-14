<?php

namespace App\Services\Clinic\Insurance;

use App\DTOs\Clinic\Insurance\InsuranceClaimData;
use App\Http\Resources\Clinic\Insurance\InsuranceClaimResource;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\Patient;
use App\Repositories\Clinic\Insurance\InsuranceClaimRepository;
use App\Repositories\Clinic\Insurance\InsuranceCompanyRepository;
use App\Support\ServiceResult;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InsuranceClaimService
{
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

            $amounts = $this->calculateAmounts($grossAmount, $dto->coveragePercentage, $dto->approvedAmount, $dto->paidAmount);
            $status = $dto->status ?? InsuranceClaim::STATUS_DRAFT;

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
        if ($nextStatus !== $claim->status && ! $this->isValidTransition($claim->status, $nextStatus)) {
            return ServiceResult::error(
                'Insurance claim status transition is not allowed.',
                null,
                ['status' => ['The requested status transition is not allowed.']],
                422
            );
        }

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

  public function updateStatus(int $claimId, string $status): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $claim = $this->repository->findForClinic($clinicId, $claimId);
    if (! $claim) {
        return ServiceResult::error('Insurance claim not found.', null, null, 404);
    }

    if ($status !== $claim->status && ! $this->isValidTransition($claim->status, $status)) {
        return ServiceResult::error(
            'Insurance claim status transition is not allowed.',
            null,
            ['status' => ['The requested status transition is not allowed.']],
            422
        );
    }

    $attributes = [
        'status' => $status,
        'updated_by' => auth()->id(),
    ];

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

    public function analytics(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $claims = InsuranceClaim::query()
            ->with('company:id,name')
            ->where('clinic_id', $clinicId)
            ->get();

        return ServiceResult::success([
            'totals_by_status' => $claims->groupBy('status')->map->count(),
            'amounts' => [
                'gross_amount' => round((float) $claims->sum('gross_amount'), 2),
                'approved_amount' => round((float) $claims->sum('approved_amount'), 2),
                'paid_amount' => round((float) $claims->sum('paid_amount'), 2),
            ],
            'top_companies' => $claims
                ->groupBy('insurance_company_id')
                ->map(function ($group) {
                    $company = $group->first()?->company;

                    return [
                        'insurance_company_id' => $company?->id,
                        'name' => $company?->name,
                        'claims_count' => $group->count(),
                        'gross_amount' => round((float) $group->sum('gross_amount'), 2),
                        'approved_amount' => round((float) $group->sum('approved_amount'), 2),
                    ];
                })
                ->sortByDesc('claims_count')
                ->values()
                ->take(5)
                ->all(),
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
            ->with('company:id,name')
            ->where('clinic_id', $clinicId)
            ->whereBetween('service_date', [$from, $to])
            ->when($filters['insurance_company_id'] ?? null, fn ($query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->get();

        $approvedStatuses = [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_APPROVED_WITH_LIMIT,
            InsuranceClaim::STATUS_PAID,
        ];
        $partialStatuses = [InsuranceClaim::STATUS_PARTIALLY_APPROVED];
        $rejectedStatuses = [InsuranceClaim::STATUS_REJECTED];
        $total = max($claims->count(), 1);

        return ServiceResult::success([
            'date_from' => $from,
            'date_to' => $to,
            'total_claims' => $claims->count(),
            'approved_count' => $claims->whereIn('status', $approvedStatuses)->count(),
            'partial_count' => $claims->whereIn('status', $partialStatuses)->count(),
            'rejected_count' => $claims->whereIn('status', $rejectedStatuses)->count(),
            'approval_rate' => round(($claims->whereIn('status', $approvedStatuses)->count() / $total) * 100, 2),
            'partial_rate' => round(($claims->whereIn('status', $partialStatuses)->count() / $total) * 100, 2),
            'rejection_rate' => round(($claims->whereIn('status', $rejectedStatuses)->count() / $total) * 100, 2),
            'by_company' => $claims->groupBy('insurance_company_id')->map(function ($group) use ($approvedStatuses, $partialStatuses, $rejectedStatuses) {
                $company = $group->first()?->company;
                $total = max($group->count(), 1);

                return [
                    'insurance_company_id' => $company?->id,
                    'name' => $company?->name,
                    'total_claims' => $group->count(),
                    'approval_rate' => round(($group->whereIn('status', $approvedStatuses)->count() / $total) * 100, 2),
                    'partial_rate' => round(($group->whereIn('status', $partialStatuses)->count() / $total) * 100, 2),
                    'rejection_rate' => round(($group->whereIn('status', $rejectedStatuses)->count() / $total) * 100, 2),
                ];
            })->values()->all(),
        ], 'Insurance approval report fetched successfully');
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

        if ($dto->appointmentId && ! ClinicAppointment::query()->where('clinic_id', $clinicId)->find($dto->appointmentId)) {
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
            $appointment = ClinicAppointment::query()->where('clinic_id', $clinicId)->find($dto->appointmentId);
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
            && ! ClinicAppointment::query()->where('clinic_id', $clinicId)->find((int) $data['appointment_id'])) {
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
