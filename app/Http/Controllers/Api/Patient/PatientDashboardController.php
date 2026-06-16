<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientDashboardResource;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\ClinicInvoice;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientDashboardController extends BasePatientController
{
    public function __invoke(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointments = $patient->appointments()->where('clinic_id', $patient->clinic_id);
        $invoices = $patient->invoices()->where('clinic_id', $patient->clinic_id);

        $latestInvoice = (clone $invoices)
            ->with(['appointment', 'doctor', 'payments'])
            ->latest('issued_at')
            ->latest('id')
            ->first();

        $latestClaim = InsuranceClaim::query()
            ->with(['company', 'appointment', 'invoice', 'items'])
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest('created_at')
            ->first();

        $data = [
            'patient' => $patient,
            'upcoming_appointments_count' => (clone $appointments)->where('appointment_at', '>=', now())->count(),
            'past_appointments_count' => (clone $appointments)->where('appointment_at', '<', now())->count(),
            'latest_appointment' => (clone $appointments)->with('doctor')->latest('appointment_at')->first(),
            'latest_invoice' => $latestInvoice,
            'total_paid' => (float) (clone $invoices)->sum('paid'),
            'remaining_balance' => (float) (clone $invoices)->sum('remaining'),
            'latest_insurance_claim' => $latestClaim,
            'documents_count' => $patient->documents()->where('clinic_id', $patient->clinic_id)->count(),
            'alerts' => $this->alerts($latestInvoice),
        ];

        return ApiResponse::success(new PatientDashboardResource($data), 'Patient dashboard retrieved successfully');
    }

    private function alerts(?ClinicInvoice $latestInvoice): array
    {
        $alerts = [];

        if ($latestInvoice && (float) $latestInvoice->remaining > 0) {
            $alerts[] = [
                'type' => 'invoice_balance',
                'message' => 'You have an outstanding invoice balance.',
                'invoice_id' => $latestInvoice->id,
            ];
        }

        return $alerts;
    }
}
