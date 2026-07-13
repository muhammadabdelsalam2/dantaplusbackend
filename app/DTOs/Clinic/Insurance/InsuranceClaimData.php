<?php

namespace App\DTOs\Clinic\Insurance;

class InsuranceClaimData
{
    public function __construct(
        public readonly int $insuranceCompanyId,
        public readonly int $patientId,
        public readonly ?int $appointmentId,
        public readonly ?int $clinicInvoiceId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $serviceDate,
        public readonly float $coveragePercentage,
        public readonly float $grossAmount,
        public readonly ?float $approvedAmount,
        public readonly ?float $paidAmount,
        public readonly ?string $status,
        public readonly ?string $notes,
        public readonly ?string $statusNotes,
    ) {
    }

    public static function fromArray(array $data): self
{
    return new self(
        insuranceCompanyId: (int) $data['insurance_company_id'],
        patientId: (int) $data['patient_id'],
        appointmentId: isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
        clinicInvoiceId: isset($data['clinic_invoice_id']) ? (int) $data['clinic_invoice_id'] : null,
        title: $data['title'],
        description: $data['description'] ?? null,
        serviceDate: $data['service_date'],
        coveragePercentage: (float) ($data['coverage_percentage'] ?? 100),
        grossAmount: (float) ($data['gross_amount'] ?? 0),
        approvedAmount: isset($data['approved_amount']) ? (float) $data['approved_amount'] : null,
        paidAmount: isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
        status: $data['status'] ?? null,
        notes: $data['notes'] ?? null,
        statusNotes: $data['status_notes'] ?? null,
    );
}

    public function toArray(): array
    {
        return [
            'insurance_company_id' => $this->insuranceCompanyId,
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'clinic_invoice_id' => $this->clinicInvoiceId,
            'title' => $this->title,
            'description' => $this->description,
            'service_date' => $this->serviceDate,
            'coverage_percentage' => $this->coveragePercentage,
            'gross_amount' => $this->grossAmount,
            'approved_amount' => $this->approvedAmount,
            'paid_amount' => $this->paidAmount,
            'status' => $this->status,
            'notes' => $this->notes,
            'status_notes' => $this->statusNotes,
        ];
    }
}
