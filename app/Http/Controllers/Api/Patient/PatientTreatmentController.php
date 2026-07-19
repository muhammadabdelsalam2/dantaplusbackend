<?php

namespace App\Http\Controllers\Api\Patient;

use App\Models\ClinicTreatment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientTreatmentController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $treatments = ClinicTreatment::query()
            ->with('doctor:id,name')
            ->where('patient_id', $patient->id)
            ->orderByDesc('treatment_date')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        $treatments->getCollection()->transform(fn (ClinicTreatment $treatment) => [
            'date' => optional($treatment->treatment_date)->toDateString(),
            'service_name' => $treatment->title,
            'doctor' => $treatment->doctor ? [
                'id' => $treatment->doctor->id,
                'name' => $treatment->doctor->name,
            ] : null,
            'notes' => $treatment->description,
            'cost' => (float) $treatment->cost,
        ]);

        return ApiResponse::success($treatments, 'Patient treatment history retrieved successfully');
    }
}
