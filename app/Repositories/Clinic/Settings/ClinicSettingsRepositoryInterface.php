<?php

namespace App\Repositories\Clinic\Settings;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

interface ClinicSettingsRepositoryInterface
{
    public function findClinicById(int $clinicId, array $with = []): ?Clinic;

    public function updateClinic(Clinic $clinic, array $data): Clinic;

    public function getSettingsGroup(int $clinicId, string $group): Collection;

    public function upsertSetting(int $clinicId, string $group, string $key, mixed $value, bool $encrypted = false): Setting;

    public function listBranches(int $clinicId): Collection;

    public function findBranchForClinic(int $clinicId, int $branchId): ?Branch;

    public function createBranch(array $data): Branch;

    public function updateBranch(Branch $branch, array $data): Branch;

    public function deleteBranch(Branch $branch): void;

    public function listManagers(int $clinicId): Collection;

    public function listDentists(int $clinicId): Collection;

    public function findDentistUserForClinic(int $clinicId, int $userId): ?User;

    public function createDentistUser(array $data): User;

    public function updateDentistUser(User $user, array $data): User;

    public function deleteDentistUser(User $user): void;

    public function createDoctorProfile(array $data): Doctor;

    public function updateDoctorProfile(Doctor $doctor, array $data): Doctor;
}
