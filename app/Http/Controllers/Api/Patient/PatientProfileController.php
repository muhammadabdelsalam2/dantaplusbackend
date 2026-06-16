<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\UpdatePatientProfileRequest;
use App\Http\Resources\Patient\PatientProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientProfileController extends BasePatientController
{
    public function show(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        return ApiResponse::success(new PatientProfileResource($patient), 'Patient profile retrieved successfully');
    }

    public function update(UpdatePatientProfileRequest $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $data = $request->validated();
        $patientData = collect($data)->except('phone')->all();
        $patient->fill($patientData);

        if (array_key_exists('phone', $data)) {
            $patient->phone = $data['phone'];
            $patient->user?->update(['phone' => $data['phone']]);
        }

        $patient->save();

        return ApiResponse::success(new PatientProfileResource($patient->fresh(['user', 'clinic'])), 'Patient profile updated successfully');
    }
}
