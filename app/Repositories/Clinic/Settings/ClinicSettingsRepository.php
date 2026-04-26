<?php

namespace App\Repositories\Clinic\Settings;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicSettingsRepository implements ClinicSettingsRepositoryInterface
{
    public function findClinicById(int $clinicId, array $with = []): ?Clinic
    {
        return Clinic::query()->with($with)->find($clinicId);
    }

    public function updateClinic(Clinic $clinic, array $data): Clinic
    {
        $clinic->update($data);

        return $clinic->refresh();
    }

    public function getSettingsGroup(int $clinicId, string $group): Collection
    {
        return Setting::query()
            ->where('scope_type', 'clinic')
            ->where('scope_id', $clinicId)
            ->where('group', $group)
            ->get();
    }

    public function upsertSetting(int $clinicId, string $group, string $key, mixed $value, bool $encrypted = false): Setting
    {
        return Setting::query()->updateOrCreate(
            [
                'scope_type' => 'clinic',
                'scope_id' => $clinicId,
                'group' => $group,
                'key' => $key,
            ],
            [
                'value' => $value,
                'is_encrypted' => $encrypted,
            ]
        );
    }

    public function listBranches(int $clinicId): Collection
    {
        return Branch::query()
            ->with('manager:id,name,email,phone')
            ->where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get();
    }

    public function findBranchForClinic(int $clinicId, int $branchId): ?Branch
    {
        return Branch::query()
            ->with('manager:id,name,email,phone')
            ->where('clinic_id', $clinicId)
            ->find($branchId);
    }

    public function createBranch(array $data): Branch
    {
        return Branch::query()->create($data);
    }

    public function updateBranch(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->refresh()->load('manager:id,name,email,phone');
    }

    public function deleteBranch(Branch $branch): void
    {
        $branch->delete();
    }

    public function listManagers(int $clinicId): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'patient'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
    }

    public function listDentists(int $clinicId): Collection
    {
        return User::query()
            ->with('doctor')
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'doctor'))
            ->orderBy('name')
            ->get();
    }

    public function findDentistUserForClinic(int $clinicId, int $userId): ?User
    {
        return User::query()
            ->with('doctor')
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'doctor'))
            ->find($userId);
    }

    public function createDentistUser(array $data): User
    {
        return User::query()->create($data);
    }

    public function updateDentistUser(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh()->load('doctor');
    }

    public function deleteDentistUser(User $user): void
    {
        $user->delete();
    }

    public function createDoctorProfile(array $data): Doctor
    {
        return Doctor::query()->create($data);
    }

    public function updateDoctorProfile(Doctor $doctor, array $data): Doctor
    {
        $doctor->update($data);

        return $doctor->refresh();
    }
}
