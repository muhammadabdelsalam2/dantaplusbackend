<?php

namespace App\Services\Owner;

use App\Models\User;
use App\Repositories\ClinicRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ClinicManagementService
{
    public function __construct(private ClinicRepository $clinicRepository)
    {
    }

    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $clinics = $this->clinicRepository->paginate($filters, $perPage);

        $data = [
            'items' => $clinics->items(),
            'pagination' => [
                'current_page' => $clinics->currentPage(),
                'last_page' => $clinics->lastPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
            ],
        ];

        return ServiceResult::success($data, 'Clinics fetched successfully');
    }

    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $modules = $data['modules'];

            $clinic = $this->clinicRepository->create([
                'name' => $data['name'],
                'owner_name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'subscription_plan' => $data['subscription_plan'],
                'payment_method' => $data['payment_method'],
                'status' => 'Trial',
                'start_date' => now(),
                'expiry_date' => now()->addDays(30),
                'max_users' => $data['max_users'],
                'max_branches' => $data['max_branches'],
            ]);

            $this->clinicRepository->syncModules($clinic, $modules);

            $adminRole = Role::firstOrCreate([
                'name' => 'Admin',
                'guard_name' => 'web',
            ]);

            $adminUser = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'password' => $data['admin_password'],
                'clinic_id' => $clinic->id,
                'is_active' => true,
            ]);

            $adminUser->assignRole($adminRole);

            $clinic = $this->clinicRepository->findById(
                $clinic->id,
                ['modules:id,clinic_id,module', 'users:id,name,email,clinic_id']
            );

            return ServiceResult::success($clinic, 'Clinic created successfully', 201);
        });
    }

    public function show(int $clinicId, string $include = ''): array
    {
        $relations = ['modules:id,clinic_id,module'];
        $includes = collect(explode(',', $include))
            ->map(fn($item) => trim($item))
            ->filter()
            ->values();

        if ($includes->contains('users')) {
            $relations[] = 'users:id,name,email,clinic_id';
        }

        if ($includes->contains('branches')) {
            $relations[] = 'branches';
        }

        $clinic = $this->clinicRepository->findById($clinicId, $relations);

        if (!$clinic) {
            return ServiceResult::error('Clinic not found', 404);
        }

        return ServiceResult::success($clinic, 'Clinic details fetched successfully');
    }

    public function update(int $clinicId, array $data): array
    {
        return DB::transaction(function () use ($clinicId, $data) {
            $clinic = $this->clinicRepository->findById($clinicId);

            if (!$clinic) {
                return ServiceResult::error('Clinic not found', 404);
            }

            $modules = Arr::pull($data, 'modules');

            $updatedClinic = $this->clinicRepository->update($clinic, $data);

            if (is_array($modules)) {
                $this->clinicRepository->syncModules($updatedClinic, $modules);
            }

            $adminUser = User::where('clinic_id', $updatedClinic->id)
                ->role('Admin')
                ->first();

            if ($adminUser) {
                $adminUser->update([
                    'name' => $updatedClinic->owner_name,
                    'email' => $updatedClinic->email,
                ]);
            }

            $fresh = $this->clinicRepository->findById($updatedClinic->id, ['modules:id,clinic_id,module']);

            return ServiceResult::success($fresh, 'Clinic updated successfully');
        });
    }

    public function updateStatus(int $clinicId, string $status): array
    {
        $clinic = $this->clinicRepository->findById($clinicId);

        if (!$clinic) {
            return ServiceResult::error('Clinic not found', 404);
        }

        $updated = $this->clinicRepository->update($clinic, ['status' => $status]);
        
        return ServiceResult::success($updated, 'Clinic status updated successfully');
    }

    public function destroy(int $clinicId): array
    {
        $clinic = $this->clinicRepository->findById($clinicId);

        if (!$clinic) {
            return ServiceResult::error('Clinic not found', 404);
        }

        $this->clinicRepository->delete($clinic);

        return ServiceResult::success(null, 'Clinic deleted successfully');
    }

    public function branches(int $clinicId): array
    {
        $clinic = $this->clinicRepository->findById($clinicId);

        if (!$clinic) {
            return ServiceResult::error('Clinic not found', 404);
        }

        $branches = $this->clinicRepository->getBranches($clinic);

        return ServiceResult::success($branches, 'Clinic branches fetched successfully');
    }
}
