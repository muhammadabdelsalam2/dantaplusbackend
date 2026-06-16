<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\CancelPatientAppointmentRequest;
use App\Http\Requests\Patient\StorePatientAppointmentRequest;
use App\Http\Resources\Patient\PatientAppointmentResource;
use App\Models\ClinicAppointment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientAppointmentController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointments = ClinicAppointment::query()
            ->with('doctor')
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->boolean('upcoming'), fn ($query) => $query->where('appointment_at', '>=', now()))
            ->when($request->boolean('past'), fn ($query) => $query->where('appointment_at', '<', now()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('appointment_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('appointment_at', '<=', $request->query('to')))
            ->latest('appointment_at')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientAppointmentResource::collection($appointments), 'Patient appointments retrieved successfully');
    }

    public function store(StorePatientAppointmentRequest $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $data = $request->validated();
        $notes = $data['notes'] ?? $data['reason'] ?? $data['complaint'] ?? null;

        $appointment = ClinicAppointment::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'doctor_user_id' => $data['doctor_user_id'] ?? $data['doctor_id'] ?? null,
            'patient_name' => $patient->user?->name ?? 'Patient #' . $patient->id,
            'patient_phone' => $patient->phone ?: $patient->user?->phone,
            'service_name' => $data['service_name'],
            'appointment_at' => $data['appointment_at'],
            'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? 30,
            'branch' => $data['branch'] ?? null,
            'room' => $data['room'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'status' => 'scheduled',
            'notes' => $notes,
        ]);

        return ApiResponse::success(new PatientAppointmentResource($appointment->load('doctor')), 'Patient appointment created successfully', 201);
    }

    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointment = $this->appointmentQuery($patient, $id)->with(['doctor', 'invoices.payments'])->first();

        if (! $appointment) {
            return ApiResponse::error('Appointment not found', 404);
        }

        return ApiResponse::success(new PatientAppointmentResource($appointment), 'Patient appointment retrieved successfully');
    }

    public function cancel(CancelPatientAppointmentRequest $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointment = $this->appointmentQuery($patient, $id)->first();

        if (! $appointment) {
            return ApiResponse::error('Appointment not found', 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled', 'attended'], true)) {
            return ApiResponse::error('This appointment cannot be cancelled', 422);
        }

        $reason = $request->validated('reason');
        $appointment->update([
            'status' => 'cancelled',
            'notes' => trim((string) $appointment->notes . ($reason ? "\nCancellation reason: {$reason}" : '')),
        ]);

        return ApiResponse::success(new PatientAppointmentResource($appointment->fresh('doctor')), 'Appointment cancelled successfully');
    }

    private function appointmentQuery($patient, int $id)
    {
        return ClinicAppointment::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id);
    }
}
