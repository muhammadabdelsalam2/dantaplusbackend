<?php

namespace App\Services\Owner;

use App\Models\DentalLab;
use App\Models\User;
use App\Repositories\DentalLabRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DentalLabManagementService
{
    public function __construct(private DentalLabRepository $dentalLabRepository)
    {
    }

    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $labs = $this->dentalLabRepository->paginate($filters, $perPage);

        $items = collect($labs->items())->map(function ($lab) {
            return [
                'id' => $lab->id,
                'name' => $lab->name,
                'contact_person' => $lab->contact_person,
                'address' => $lab->address,
                'city' => $lab->city,
                'phone' => $lab->phone,
                'email' => $lab->email,
                'working_hours' => $lab->working_hours,
                'avg_delivery_days' => $lab->avg_delivery_days,
                'response_speed' => $lab->response_speed,
                'status' => $lab->status,
                'logo_url' => $lab->logo_url,
                'rating' => $lab->rating,
                'is_external' => $lab->is_external,
                'date_added' => $lab->date_added,
                'on_time_percentage' => $lab->on_time_percentage,
                'rejection_rate' => $lab->rejection_rate,
                'created_at' => $lab->created_at,
                'updated_at' => $lab->updated_at,
                'deleted_at' => $lab->deleted_at,
                'active_clinics' => $lab->active_clinics,
                'services' => $lab->services,
            ];
        })->values();

        $data = [
            'items' => $items,
            'pagination' => [
                'current_page' => $labs->currentPage(),
                'last_page' => $labs->lastPage(),
                'per_page' => $labs->perPage(),
                'total' => $labs->total(),
            ],
            'stats' => $this->dentalLabRepository->stats(),
        ];

        return ServiceResult::success($data, 'Dental labs fetched successfully');
    }

    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $data['logo_url'] = $this->storeLogo($data['logo']);
            }

            $services = Arr::pull($data, 'services', []);
            $adminName = Arr::pull($data, 'admin_name');
            $adminEmail = Arr::pull($data, 'admin_email');
            $adminPassword = Arr::pull($data, 'admin_password');
            $adminIsActive = (int) Arr::pull($data, 'admin_is_active', 1);

            unset($data['logo']);

            $lab = $this->dentalLabRepository->create([
                'name' => $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'working_hours' => $data['working_hours'] ?? null,
                'avg_delivery_days' => $data['avg_delivery_days'],
                'response_speed' => $data['response_speed'] ?? null,
                'status' => $data['status'] ?? DentalLab::STATUS_ACTIVE,
                'logo_url' => $data['logo_url'] ?? null,
                'rating' => $data['rating'] ?? null,
                'is_external' => (bool) ($data['is_external'] ?? false),
                'date_added' => $data['date_added'] ?? null,
                'on_time_percentage' => $data['on_time_percentage'] ?? null,
                'rejection_rate' => $data['rejection_rate'] ?? null,
            ]);

            $this->dentalLabRepository->replaceServices($lab, $services);

            $createdUser = null;

            // Backward-compatible:
            // create lab login account only if admin credentials are sent
            if (!empty($adminEmail) && !empty($adminPassword)) {
                $labRole = Role::firstOrCreate([
                    'name' => 'lab',
                    'guard_name' => 'web',
                ]);

                $createdUser = User::create([
                    'name' => $adminName ?: ($data['contact_person'] ?? $data['name']),
                    'email' => $adminEmail,
                    'password' => $adminPassword,
                    'is_active' => $adminIsActive,
                    'lab_id' => $lab->id,
                ]);

                $createdUser->syncRoles([$labRole->name]);
            }

            $fresh = $this->dentalLabRepository->findById($lab->id, [
                'services:id,lab_id,name,price,turnaround_days'
            ]);

            $payload = $fresh ? $fresh->toArray() : [];

            $payload['login_account_created'] = $createdUser !== null;
            $payload['login_account'] = $createdUser ? [
                'id' => $createdUser->id,
                'name' => $createdUser->name,
                'email' => $createdUser->email,
                'is_active' => (bool) $createdUser->is_active,
                'lab_id' => $createdUser->lab_id,
                'role' => 'lab',
            ] : null;

            return ServiceResult::success($payload, 'Dental lab created successfully', 201);
        });
    }

    public function show(int $labId, string $include = ''): array
    {
        $requestedIncludes = collect(explode(',', $include))
            ->map(fn($item) => trim($item))
            ->filter()
            ->values();

        if ($requestedIncludes->isEmpty()) {
            $requestedIncludes = collect(['services', 'reviews', 'performance']);
        }

        $relations = [];

        if ($requestedIncludes->contains('services')) {
            $relations['services'] = fn($q) => $q
                ->select(['id', 'lab_id', 'name', 'price', 'turnaround_days'])
                ->orderBy('name');
        }

        if ($requestedIncludes->contains('reviews')) {
            $relations['reviews'] = fn($q) => $q
                ->select(['id', 'lab_id', 'user_name', 'rating', 'comment', 'reviewed_at', 'created_at'])
                ->latest('reviewed_at')
                ->latest('id')
                ->limit(3);
        }

        if ($requestedIncludes->contains('partnerships')) {
            $relations['partnerships'] = fn($q) => $q
                ->with('clinic:id,name')
                ->orderByDesc('id');
        }

        $lab = $this->dentalLabRepository->findById($labId, $relations);

        if (!$lab) {
            return ServiceResult::error('Dental lab not found', null, null, 404);
        }

        $payload = [
            'id' => $lab->id,
            'name' => $lab->name,
            'contact_person' => $lab->contact_person,
            'address' => $lab->address,
            'city' => $lab->city,
            'phone' => $lab->phone,
            'email' => $lab->email,
            'working_hours' => $lab->working_hours,
            'logo_url' => $lab->logo_url,
            'status' => $lab->status,
            'active_clinics' => $lab->active_clinics,
        ];

        if ($requestedIncludes->contains('performance')) {
            $payload['performance'] = [
                'avg_delivery_days' => $lab->avg_delivery_days,
                'on_time_percentage' => $lab->on_time_percentage,
                'response_speed' => $lab->response_speed,
                'rejection_rate' => $lab->rejection_rate,
                'rating' => $lab->rating,
            ];
        }

        if ($requestedIncludes->contains('services')) {
            $payload['services'] = $lab->services;
        }

        if ($requestedIncludes->contains('reviews')) {
            $payload['last_reviews'] = $lab->reviews;
        }

        if ($requestedIncludes->contains('partnerships')) {
            $payload['partnerships'] = $lab->partnerships;
        }

        return ServiceResult::success($payload, 'Dental lab details fetched successfully');
    }

    public function update(int $labId, array $data): array
    {
        return DB::transaction(function () use ($labId, $data) {
            $lab = $this->dentalLabRepository->findById($labId);

            if (!$lab) {
                return ServiceResult::error('Dental lab not found', null, null, 404);
            }

            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $this->deletePublicFile($lab->logo_url ?? null);
                $data['logo_url'] = $this->storeLogo($data['logo']);
            }

            $services = Arr::pull($data, 'services', null);
            unset($data['logo']);

            $updated = $this->dentalLabRepository->update($lab, $data);

            if (is_array($services)) {
                $this->dentalLabRepository->replaceServices($updated, $services);
            }

            $fresh = $this->dentalLabRepository->findById($updated->id, [
                'services:id,lab_id,name,price,turnaround_days'
            ]);

            return ServiceResult::success($fresh, 'Dental lab updated successfully');
        });
    }

    public function destroy(int $labId): array
    {
        $lab = $this->dentalLabRepository->findById($labId);

        if (!$lab) {
            return ServiceResult::error('Dental lab not found', null, null, 404);
        }

        $this->deletePublicFile($lab->logo_url ?? null);
        $this->dentalLabRepository->delete($lab);

        return ServiceResult::success(null, 'Dental lab deleted successfully');
    }

    public function updateStatus(int $labId, string $status): array
    {
        $lab = $this->dentalLabRepository->findById($labId);

        if (!$lab) {
            return ServiceResult::error('Dental lab not found', null, null, 404);
        }

        $updated = $this->dentalLabRepository->update($lab, ['status' => $status]);

        return ServiceResult::success($updated, 'Dental lab status updated successfully');
    }

    public function bulkStatus(array $ids, string $status): array
    {
        $updatedCount = $this->dentalLabRepository->bulkUpdateStatus($ids, $status);

        return ServiceResult::success([
            'updated_count' => $updatedCount,
        ], 'Dental labs status updated successfully');
    }

    public function bulkDelete(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $labs = $this->dentalLabRepository->findByIds($ids);

            foreach ($labs as $lab) {
                $this->deletePublicFile($lab->logo_url ?? null);
            }

            $deletedCount = $this->dentalLabRepository->bulkDelete($ids);

            return ServiceResult::success([
                'deleted_count' => $deletedCount,
            ], 'Dental labs deleted successfully');
        });
    }

    private function storeLogo(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;
        $path = 'dental-labs/logos/' . $filename;

        Storage::disk('public')->putFileAs('dental-labs/logos', $file, $filename);

        return $path;
    }

    private function deletePublicFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
