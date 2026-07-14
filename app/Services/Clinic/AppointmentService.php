<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\AppointmentResource;
use App\Models\ClinicAppointment;
use App\Models\Patient;
use App\Models\User;
use App\Support\ServiceResult;
use Carbon\Carbon;

class AppointmentService
{
   public function index(array $filters = []): array
{
    if (! $this->currentClinicId()) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $query = ClinicAppointment::query()
        ->with(['doctor:id,name', 'patient.user:id,name'])
        ->where('clinic_id', $this->currentClinicId());

    if (! empty($filters['year'])) {
        $query->whereYear('appointment_at', $filters['year']);
    }

    if (! empty($filters['month'])) {
        $query->whereMonth('appointment_at', $filters['month']);
    }

    if (! empty($filters['day'])) {
        $query->whereDay('appointment_at', $filters['day']);
    }

    if (! empty($filters['date']) && ($filters['view'] ?? null) === 'month') {
        $date = Carbon::parse($filters['date']);
        $query->whereBetween('appointment_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]);
    } elseif (! empty($filters['date']) && ($filters['view'] ?? null) === 'week') {
        $date = Carbon::parse($filters['date']);
        $query->whereBetween('appointment_at', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]);
    } elseif (! empty($filters['date']) && empty($filters['year']) && empty($filters['month']) && empty($filters['day'])) {
        $date = Carbon::parse($filters['date']);
        $query->whereBetween('appointment_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::parse($filters['end_date'])->startOfDay();
        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : $startDate->copy()->endOfDay();

        $query->whereBetween('appointment_at', [$startDate, $endDate]);
    }

    if (! empty($filters['branch'])) {
        $query->where('branch', $filters['branch']);
    }

   if (! empty($filters['room_id'])) {
    $query->where('room_id', $filters['room_id']);
} elseif (! empty($filters['room'])) {
    $query->where('room', $filters['room']);
}

    if (! empty($filters['search'])) {
        $search = $filters['search'];

        $query->where(function ($q) use ($search) {
            $q->where('patient_name', 'like', "%{$search}%")
              ->orWhereHas('doctor', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
        });
    }

    $appointments = $query->latest('appointment_at')->get();

    return ServiceResult::success(
        AppointmentResource::collection($appointments)->resolve(),
        'Appointments fetched successfully'
    );
}

    public function show(int $id): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        return ServiceResult::success((new AppointmentResource($appointment))->resolve(), 'Appointment fetched successfully');
    }

    public function create(array $data): array
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

        $doctor = ! empty($data['doctor_id'])
            ? User::query()->where('clinic_id', $clinicId)->role('doctor')->find($data['doctor_id'])
            : null;

        if (! empty($data['doctor_id']) && ! $doctor) {
            return ServiceResult::error('Doctor not found.', null, ['doctor_id' => ['Doctor not found.']], 422);
        }

        $appointment = ClinicAppointment::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient?->id,
            'doctor_user_id' => $doctor?->id,
            'patient_name' => $patient?->user?->name ?? $data['patient_name'],
            'patient_phone' => $patient?->phone ?? $patient?->user?->phone ?? ($data['patient_phone'] ?? null),
            'service_name' => $data['service_name'],
            'appointment_at' => $data['appointment_at'],
            'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? 30,
            'duration' => $data['duration'] ?? $data['duration_minutes'] ?? 30,
            'branch' => $data['branch'] ?? null,
            'room' => $data['room'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->show($appointment->id);
    }

    public function update(int $id, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if (array_key_exists('patient_id', $data) && ! empty($data['patient_id'])) {
            $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);
            if (! $patient) {
                return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
            }

            $data['patient_name'] = $patient->user?->name ?? $appointment->patient_name;
            $data['patient_phone'] = $patient->phone ?? $patient->user?->phone ?? $appointment->patient_phone;
        }

        if (array_key_exists('doctor_id', $data) && ! empty($data['doctor_id'])) {
            $doctor = User::query()->where('clinic_id', $clinicId)->role('doctor')->find($data['doctor_id']);
            if (! $doctor) {
                return ServiceResult::error('Doctor not found.', null, ['doctor_id' => ['Doctor not found.']], 422);
            }

            $data['doctor_user_id'] = $doctor->id;
        }

        if (array_key_exists('duration', $data) && ! array_key_exists('duration_minutes', $data)) {
            $data['duration_minutes'] = $data['duration'];
        }

        if (array_key_exists('duration_minutes', $data) && ! array_key_exists('duration', $data)) {
            $data['duration'] = $data['duration_minutes'];
        }

        unset($data['doctor_id']);

        $appointment->update($data);

        return $this->show($appointment->id);
    }
    // AppointmentService.php — ميثود جديدة
public function approve(int $id, array $data = []): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $appointment = $this->findClinicAppointment($id);
    if (! $appointment) {
        return ServiceResult::error('Appointment not found.', null, null, 404);
    }

    if ($appointment->status !== 'pending') {
        return ServiceResult::error('Only pending appointments can be approved.', null, null, 422);
    }

    $data['status'] = 'scheduled';


    return $this->update($id, $data);
}

    private function findClinicAppointment(int $id): ?ClinicAppointment
    {
        return ClinicAppointment::query()
            ->with(['doctor:id,name', 'patient.user:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($id);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
