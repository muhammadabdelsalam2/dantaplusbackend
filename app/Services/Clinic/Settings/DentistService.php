<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\DentistResource;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DentistService
{
    public function __construct(private ClinicSettingsRepositoryInterface $repository)
    {
    }

    public function index(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            DentistResource::collection($this->repository->listDentists($clinicId))->resolve(),
            'Dentists fetched successfully'
        );
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $user = DB::transaction(function () use ($clinicId, $data) {
            $user = $this->repository->createDentistUser([
                'clinic_id' => $clinicId,
                'name' => $data['name'],
                'username' => Str::slug($data['name'], '') ?: ('doctor' . now()->timestamp),
                'email' => $data['email'] ?? ('doctor-' . Str::lower(Str::random(8)) . '@dentaplus.local'),
                'phone' => $data['phone'] ?? null,
                'password' => bcrypt(Str::random(12)),
                'status' => 'Active',
                'is_active' => true,
                'is_verified' => true,
                'role' => 'doctor',
                'commission_rates' => [
                    'insurance_commission' => (float) ($data['insurance_commission'] ?? 15),
                    'cash_commission' => (float) ($data['cash_commission'] ?? 20),
                ],
            ]);

            $user->syncRoles(['doctor']);

            $this->repository->createDoctorProfile([
                'user_id' => $user->id,
                'specialization' => $data['specialization'] ?? 'General Dentistry',
                'license_number' => 'DOC-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)),
                'working_hours_from' => $data['working_hours_from'] ?? null,
                'working_hours_to' => $data['working_hours_to'] ?? null,
            ]);

            return $user->fresh()->load('doctor');
        });

        return ServiceResult::success((new DentistResource($user))->resolve(), 'Dentist created successfully', 201);
    }

    public function show(int $id): array
    {
        $user = $this->resolveDentist($id);
        if (! $user) {
            return ServiceResult::error('Dentist not found.', null, null, 404);
        }

        return ServiceResult::success((new DentistResource($user))->resolve(), 'Dentist fetched successfully');
    }

    public function update(int $id, array $data): array
    {
        $user = $this->resolveDentist($id);
        if (! $user) {
            return ServiceResult::error('Dentist not found.', null, null, 404);
        }

        $payload = [];

        foreach (['name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('insurance_commission', $data) || array_key_exists('cash_commission', $data)) {
            $existingRates = is_array($user->commission_rates) ? $user->commission_rates : [];
            $payload['commission_rates'] = [
                'insurance_commission' => (float) ($data['insurance_commission'] ?? ($existingRates['insurance_commission'] ?? 15)),
                'cash_commission' => (float) ($data['cash_commission'] ?? ($existingRates['cash_commission'] ?? 20)),
            ];
        }

        DB::transaction(function () use ($user, $payload, $data) {
            if (! empty($payload)) {
                $this->repository->updateDentistUser($user, $payload);
            }

            if (
                array_key_exists('specialization', $data)
                || array_key_exists('working_hours_from', $data)
                || array_key_exists('working_hours_to', $data)
            ) {
                $doctor = $user->doctor ?: $this->repository->createDoctorProfile([
                    'user_id' => $user->id,
                    'specialization' => $data['specialization'] ?? 'General Dentistry',
                    'license_number' => 'DOC-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)),
                    'working_hours_from' => $data['working_hours_from'] ?? null,
                    'working_hours_to' => $data['working_hours_to'] ?? null,
                ]);

                if ($user->doctor) {
                    $doctorPayload = [];

                    foreach (['specialization', 'working_hours_from', 'working_hours_to'] as $field) {
                        if (array_key_exists($field, $data)) {
                            $doctorPayload[$field] = $data[$field];
                        }
                    }

                    $this->repository->updateDoctorProfile($doctor, $doctorPayload);
                }
            }
        });

        return ServiceResult::success((new DentistResource($user->fresh()->load('doctor')))->resolve(), 'Dentist updated successfully');
    }

    public function destroy(int $id): array
    {
        $user = $this->resolveDentist($id);
        if (! $user) {
            return ServiceResult::error('Dentist not found.', null, null, 404);
        }

        $this->repository->deleteDentistUser($user);

        return ServiceResult::success(null, 'Dentist deleted successfully');
    }

    private function resolveDentist(int $id)
    {
        $clinicId = $this->currentClinicId();

        return $clinicId ? $this->repository->findDentistUserForClinic($clinicId, $id) : null;
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
