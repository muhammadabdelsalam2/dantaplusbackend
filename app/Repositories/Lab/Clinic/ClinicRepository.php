<?php

namespace App\Repositories\Lab\Clinic;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\ClinicLabPartnership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClinicRepository implements ClinicRepositoryInterface
{
    public function paginateByLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Clinic::query()
            ->whereHas('labPartnerships', fn ($q) => $q->where('lab_id', $labId))
            ->with([
                'labPartnerships' => fn ($q) => $q->where('lab_id', $labId),
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                ($filters['status'] ?? null) && !in_array($filters['status'], ['all', 'All']),
                function (Builder $query) use ($filters, $labId) {
                    $status = $filters['status'];

                    $query->whereHas('labPartnerships', function ($q) use ($labId, $status) {
                        $q->where('lab_id', $labId)
                          ->where('status', $status);
                    });
                }
            )
            ->when(
                ($filters['type'] ?? null) && !in_array($filters['type'], ['all', 'All']),
                function (Builder $query) use ($filters) {
                    $type = $filters['type'];

                    if ($type === 'external') {
                        $query->where('is_external', true);
                    } elseif ($type === 'internal') {
                        $query->where('is_external', false);
                    } else {
                        $query->where('clinic_type', $type);
                    }
                }
            )
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getStats(int $labId): array
    {
        $base = ClinicLabPartnership::query()->where('lab_id', $labId);

        return [
            'total' => (int) $base->count(),
            'active' => (int) (clone $base)->where('status', 'Active')->count(),
            'pending' => (int) (clone $base)->where('status', 'Pending')->count(),
        ];
    }

 public function findPartnerClinic(int $labId, int $clinicId): ?Clinic
{
    return Clinic::query()
        ->where('id', $clinicId)
        ->whereHas('labPartnerships', fn ($q) => $q->where('lab_id', $labId))
        ->with([
            'labPartnerships' => fn ($q) => $q->where('lab_id', $labId),
        ])
        ->first();
}

    public function findInternalClinicByEmail(string $email): ?Clinic
    {
        return Clinic::query()
            ->where('email', $email)
            ->where('is_external', false)
            ->first();
    }

    public function partnershipExists(int $labId, int $clinicId, array $statuses): bool
    {
        return ClinicLabPartnership::query()
            ->where('lab_id', $labId)
            ->where('clinic_id', $clinicId)
            ->whereIn('status', $statuses)
            ->exists();
    }

    public function createClinic(array $data): Clinic
    {
        return Clinic::query()->create($data);
    }

    public function createPartnership(array $data): ClinicLabPartnership
    {
        return ClinicLabPartnership::query()->create($data);
    }

    public function getCasesForClinic(int $labId, int $clinicId, int $perPage = 15): LengthAwarePaginator
    {
        return CaseModel::query()
            ->with(['patient.user:id,name'])
            ->where('lab_id', $labId)
            ->where('clinic_id', $clinicId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findPartnership(int $labId, int $clinicId): ?ClinicLabPartnership
    {
        return ClinicLabPartnership::query()
            ->with('clinic')
            ->where('lab_id', $labId)
            ->where('clinic_id', $clinicId)
            ->first();
    }

    public function updatePartnership(ClinicLabPartnership $partnership, array $data): ClinicLabPartnership
    {
        $partnership->update($data);

        return $partnership->refresh();
    }
}
