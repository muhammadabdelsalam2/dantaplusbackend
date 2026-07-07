<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\TreatmentResource;
use App\Models\ClinicTreatment;
use App\Models\Patient;
use App\Models\User;
use App\Support\ServiceResult;

class TreatmentService
{
    public function index(): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = ClinicTreatment::query()
            ->with(['patient.user:id,name', 'doctor:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->latest('id')
            ->get();

        return ServiceResult::success(TreatmentResource::collection($rows)->resolve(), 'Treatments fetched successfully');
    }

    public function show(int $id): array
    {
        if (! $this->currentClinicId()) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $treatment = $this->findClinicTreatment($id);
        if (! $treatment) {
            return ServiceResult::error('Treatment not found.', null, null, 404);
        }

        return ServiceResult::success((new TreatmentResource($treatment))->resolve(), 'Treatment fetched successfully');
    }

    public function create(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $doctor = ! empty($data['doctor_id'])
            ? User::query()->where('clinic_id', $clinicId)->role('doctor')->find($data['doctor_id'])
            : null;

        if (! empty($data['doctor_id']) && ! $doctor) {
            return ServiceResult::error('Doctor not found.', null, ['doctor_id' => ['Doctor not found.']], 422);
        }

        $treatment = ClinicTreatment::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'doctor_user_id' => $doctor?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'tooth_number' => $data['tooth_number'] ?? null,
            'sessions_count' => $data['sessions_count'] ?? 1,
            'treatment_date' => $data['treatment_date'] ?? null,
            'cost' => $data['cost'] ?? 0,
            'status' => $data['status'],
        ]);

        return $this->show($treatment->id);
    }

    private function findClinicTreatment(int $id): ?ClinicTreatment
    {
        return ClinicTreatment::query()
            ->with(['patient.user:id,name', 'doctor:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($id);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
    public function indexForPatient(int $patientId): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $patient = Patient::query()->where('clinic_id', $clinicId)->find($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $rows = ClinicTreatment::query()
        ->with(['patient.user:id,name', 'doctor:id,name'])
        ->where('clinic_id', $clinicId)
        ->where('patient_id', $patient->id)
        ->latest('id')
        ->get();

    return ServiceResult::success(TreatmentResource::collection($rows)->resolve(), 'Patient treatments fetched successfully');
}

public function createForPatient(int $patientId, array $data): array
{
    $data['patient_id'] = $patientId;

    return $this->create($data);
}
}
