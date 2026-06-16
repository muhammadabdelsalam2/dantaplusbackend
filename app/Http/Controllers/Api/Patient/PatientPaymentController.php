<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\StorePatientRefundRequest;
use App\Http\Resources\Patient\PatientPaymentResource;
use App\Models\ClinicPayment;
use App\Models\PatientPaymentRefundRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientPaymentController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $payments = ClinicPayment::query()
            ->with('invoice')
            ->where('clinic_id', $patient->clinic_id)
            ->whereHas('invoice', fn ($query) => $query->where('patient_id', $patient->id))
            ->latest('paid_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientPaymentResource::collection($payments), 'Patient payments retrieved successfully');
    }

    public function refundRequest(StorePatientRefundRequest $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $payment = ClinicPayment::query()
            ->with('invoice')
            ->where('id', $id)
            ->where('clinic_id', $patient->clinic_id)
            ->whereHas('invoice', fn ($query) => $query->where('patient_id', $patient->id))
            ->first();

        if (! $payment || ! $payment->invoice) {
            return ApiResponse::error('Payment not found', 404);
        }

        $refund = PatientPaymentRefundRequest::create([
            'patient_id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'payment_id' => $payment->id,
            'invoice_id' => $payment->clinic_invoice_id,
            'reason' => $request->validated('reason'),
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return ApiResponse::success([
            'id' => $refund->id,
            'status' => $refund->status,
            'requested_at' => optional($refund->requested_at)?->toISOString(),
        ], 'Refund request submitted successfully', 201);
    }
}
