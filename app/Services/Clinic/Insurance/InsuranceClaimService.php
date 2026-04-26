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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $claim = DB::transaction(function () use ($clinicId, $dto) {
            $amounts = $this->calculateAmounts($dto->grossAmount, $dto->coveragePercentage, $dto->approvedAmount, $dto->paidAmount);
            $status = $dto->status ?? InsuranceClaim::STATUS_DRAFT;

            return $this->repository->create([
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
                'gross_amount' => $dto->grossAmount,
                'patient_share_amount' => $amounts['patient_share_amount'],
                'insurance_share_amount' => $amounts['insurance_share_amount'],
                'approved_amount' => $amounts['approved_amount'],
                'paid_amount' => $amounts['paid_amount'],
                'status' => $status,
                'notes' => $dto->notes,
                'status_notes' => $dto->statusNotes,
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

        $nextStatus = $data['status'] ?? $claim->status;
        if ($nextStatus !== $claim->status && ! $this->isValidTransition($claim->status, $nextStatus)) {
            return ServiceResult::error(
                'Insurance claim status transition is not allowed.',
                null,
                ['status' => ['The requested status transition is not allowed.']],
                422
            );
        }

        $updatedClaim = DB::transaction(function () use ($claim, $data, $nextStatus) {
            $grossAmount = isset($data['gross_amount']) ? (float) $data['gross_amount'] : (float) $claim->gross_amount;
            $coveragePercentage = isset($data['coverage_percentage']) ? (float) $data['coverage_percentage'] : (float) $claim->coverage_percentage;
            $approvedAmount = array_key_exists('approved_amount', $data) ? (($data['approved_amount'] === null) ? null : (float) $data['approved_amount']) : (float) $claim->approved_amount;
            $paidAmount = array_key_exists('paid_amount', $data) ? (($data['paid_amount'] === null) ? null : (float) $data['paid_amount']) : (float) $claim->paid_amount;

            $amounts = $this->calculateAmounts($grossAmount, $coveragePercentage, $approvedAmount, $paidAmount);

            $attributes = array_merge($data, [
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

            return $this->repository->update($claim, $attributes);
        });

        return ServiceResult::success(
            (new InsuranceClaimResource($updatedClaim))->resolve(),
            'Insurance claim updated successfully'
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
                InsuranceClaim::STATUS_REJECTED,
                InsuranceClaim::STATUS_CANCELLED,
            ],
            InsuranceClaim::STATUS_APPROVED => [
                InsuranceClaim::STATUS_PAID,
            ],
            InsuranceClaim::STATUS_PARTIALLY_APPROVED => [
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
}
