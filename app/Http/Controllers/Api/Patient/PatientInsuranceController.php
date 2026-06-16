<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientDocumentResource;
use App\Http\Resources\Patient\PatientInsuranceClaimResource;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\PatientDocument;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientInsuranceController extends BasePatientController
{
    public function claims(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $claims = $this->claimQuery($patient)
            ->with(['company', 'appointment', 'invoice'])
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientInsuranceClaimResource::collection($claims), 'Patient insurance claims retrieved successfully');
    }

    public function showClaim(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $claim = $this->claimQuery($patient)
            ->with(['company', 'appointment', 'invoice', 'items', 'patientConsent'])
            ->where('id', $id)
            ->first();

        if (! $claim) {
            return ApiResponse::error('Insurance claim not found', 404);
        }

        return ApiResponse::success(new PatientInsuranceClaimResource($claim), 'Patient insurance claim retrieved successfully');
    }

    public function consents(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $consentIds = $this->claimQuery($patient)
            ->whereNotNull('patient_consent_document_id')
            ->pluck('patient_consent_document_id');

        $documents = PatientDocument::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->whereIn('id', $consentIds)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientDocumentResource::collection($documents), 'Patient insurance consents retrieved successfully');
    }

    private function claimQuery($patient)
    {
        return InsuranceClaim::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id);
    }
}
