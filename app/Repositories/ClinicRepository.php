<?php

namespace App\Repositories;

use App\Models\Clinic;
use App\Repositories\Contracts\ClinicRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClinicRepository implements ClinicRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Clinic::query()
            ->withCount(['branches', 'users'])
            ->with('modules:id,clinic_id,module')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when($filters['subscription_plan'] ?? null, fn($query, $plan) => $query->where('subscription_plan', $plan))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $clinicId, array $with = []): ?Clinic
    {
        return Clinic::with($with)->find($clinicId);
    }

    public function create(array $data): Clinic
    {
        return Clinic::create($data);
    }

    public function update(Clinic $clinic, array $data): Clinic
    {
        $clinic->update($data);

        return $clinic->refresh();
    }

    public function delete(Clinic $clinic): void
    {
        $clinic->delete();
    }

    public function syncModules(Clinic $clinic, array $modules): void
    {
        $clinic->modules()->delete();

        $rows = collect($modules)
            ->filter()
            ->map(fn(string $module) => [
                'clinic_id' => $clinic->id,
                'module' => $module,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            $clinic->modules()->insert($rows);
        }
    }

    public function getBranches(Clinic $clinic): Collection
    {
        return $clinic->branches()
            ->with('manager:id,name,email')
            ->orderBy('name')
            ->get();
    }
}
