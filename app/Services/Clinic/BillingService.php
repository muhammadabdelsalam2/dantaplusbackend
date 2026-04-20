<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicInvoiceResource;
use App\Http\Resources\Clinic\ClinicPaymentResource;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicPayment;
use App\Models\Patient;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function indexInvoices(): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = ClinicInvoice::query()
            ->with(['patient.user:id,name', 'payments.recorder:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->latest('id')
            ->get();

        return ServiceResult::success(ClinicInvoiceResource::collection($rows)->resolve(), 'Invoices fetched successfully');
    }

    public function showInvoice(int $id): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $invoice = $this->findClinicInvoice($id);
        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        return ServiceResult::success((new ClinicInvoiceResource($invoice))->resolve(), 'Invoice fetched successfully');
    }

    public function createInvoice(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $patient = ! empty($data['patient_id'])
            ? Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id'])
            : null;

        if (! empty($data['patient_id']) && ! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $appointment = ! empty($data['appointment_id'])
            ? ClinicAppointment::query()->where('clinic_id', $clinicId)->find($data['appointment_id'])
            : null;

        if (! empty($data['appointment_id']) && ! $appointment) {
            return ServiceResult::error('Appointment not found.', null, ['appointment_id' => ['Appointment not found.']], 422);
        }

        $paid = (float) ($data['paid'] ?? 0);
        $total = (float) $data['total'];

        $invoice = ClinicInvoice::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient?->id,
            'appointment_id' => $appointment?->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => max($total - $paid, 0),
            'status' => $paid >= $total && $total > 0 ? 'paid' : 'pending',
            'payment_method' => $data['payment_method'] ?? null,
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->showInvoice($invoice->id);
    }

    public function recordPayment(int $invoiceId, array $data): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $invoice = $this->findClinicInvoice($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        $payment = DB::transaction(function () use ($invoice, $data) {
            $payment = ClinicPayment::query()->create([
                'clinic_invoice_id' => $invoice->id,
                'clinic_id' => $invoice->clinic_id,
                'recorded_by' => auth()->id(),
                'amount' => $data['amount'],
                'method' => $data['method'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaid = (float) $invoice->paid + (float) $payment->amount;
            $invoice->update([
                'paid' => $newPaid,
                'remaining' => max((float) $invoice->total - $newPaid, 0),
                'status' => $newPaid >= (float) $invoice->total ? 'paid' : 'pending',
            ]);

            return $payment->fresh('recorder:id,name');
        });

        return ServiceResult::success((new ClinicPaymentResource($payment))->resolve(), 'Payment recorded successfully', 201);
    }

    private function findClinicInvoice(int $id): ?ClinicInvoice
    {
        return ClinicInvoice::query()
            ->with(['patient.user:id,name', 'payments.recorder:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($id);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (ClinicInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }
}
