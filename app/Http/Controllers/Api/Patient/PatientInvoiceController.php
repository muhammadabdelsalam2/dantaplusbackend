<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientInvoiceResource;
use App\Models\ClinicInvoice;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientInvoiceController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $invoices = $this->invoiceQuery($patient)
            ->with(['appointment', 'doctor', 'payments'])
            ->latest('issued_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientInvoiceResource::collection($invoices), 'Patient invoices retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $invoice = $this->invoiceQuery($patient)
            ->with(['appointment', 'doctor', 'payments'])
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return ApiResponse::error('Invoice not found', 404);
        }

        return ApiResponse::success(new PatientInvoiceResource($invoice), 'Patient invoice retrieved successfully');
    }

    private function invoiceQuery($patient)
    {
        return ClinicInvoice::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id);
    }
}
