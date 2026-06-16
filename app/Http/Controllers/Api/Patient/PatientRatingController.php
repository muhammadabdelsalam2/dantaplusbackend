<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\StorePatientAppointmentRatingRequest;
use App\Http\Resources\Patient\PatientRatingResource;
use App\Models\ClinicAppointment;
use App\Models\PatientAppointmentRating;
use App\Support\ApiResponse;

class PatientRatingController extends BasePatientController
{
    public function store(StorePatientAppointmentRatingRequest $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointment = ClinicAppointment::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->first();

        if (! $appointment) {
            return ApiResponse::error('Appointment not found', 404);
        }

        if (! in_array($appointment->status, ['completed', 'attended'], true)) {
            return ApiResponse::error('Appointment can only be rated after the visit is completed', 422);
        }

        $data = $request->validated();
        $rating = PatientAppointmentRating::firstOrCreate(
            [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
            ],
            [
                'clinic_id' => $patient->clinic_id,
                'doctor_user_id' => $appointment->doctor_user_id,
                'doctor_rating' => $data['doctor_rating'],
                'clinic_rating' => $data['clinic_rating'],
                'comment' => $data['comment'] ?? null,
            ]
        );

        if (! $rating->wasRecentlyCreated) {
            return ApiResponse::error('This appointment has already been rated', 422);
        }

        return ApiResponse::success(new PatientRatingResource($rating), 'Appointment rating submitted successfully', 201);
    }
}
