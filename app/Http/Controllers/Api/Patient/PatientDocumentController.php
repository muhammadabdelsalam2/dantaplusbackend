<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientDocumentResource;
use App\Models\PatientDocument;
use App\Models\PatientNote;
use App\Models\PatientRadiology;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientDocumentController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $documents = PatientDocument::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientDocumentResource::collection($documents), 'Patient documents retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $document = PatientDocument::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->first();

        if (! $document) {
            return ApiResponse::error('Document not found', 404);
        }

        return ApiResponse::success(new PatientDocumentResource($document), 'Patient document retrieved successfully');
    }

    public function radiology(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $radiology = PatientRadiology::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success($radiology, 'Patient radiology files retrieved successfully');
    }

    public function notes(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $notes = PatientNote::query()
            ->select(['id', 'patient_id', 'clinic_id', 'note', 'created_at'])
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success($notes, 'Patient medical notes retrieved successfully');
    }
}
