<?php

namespace App\Repositories;

use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\DentalLabReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DentalLabRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return DentalLab::query()
            ->withCount([
                'partnerships as active_clinics' => fn($q) => $q->where('status', ClinicLabPartnership::STATUS_ACTIVE),
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function stats(): array
    {
        $averageRating = DentalLab::query()->whereNotNull('rating')->avg('rating');

        if ($averageRating === null) {
            $averageRating = DentalLabReview::query()->avg('rating');
        }

        return [
            'total_registered_labs' => DentalLab::count(),
            'active_collaborations' => ClinicLabPartnership::query()->where('status', ClinicLabPartnership::STATUS_ACTIVE)->count(),
            'avg_delivery_speed' => round((float) (DentalLab::query()->avg('avg_delivery_days') ?? 0), 2),
            'average_rating' => round((float) ($averageRating ?? 0), 2),
        ];
    }

    public function findById(int $labId, array $with = []): ?DentalLab
    {
        return DentalLab::withCount([
            'partnerships as active_clinics' => fn($q) => $q->where('status', ClinicLabPartnership::STATUS_ACTIVE),
        ])->with($with)->find($labId);
    }

    public function create(array $data): DentalLab
    {
        return DentalLab::create($data);
    }

    public function update(DentalLab $lab, array $data): DentalLab
    {
        $lab->update($data);

        return $lab->refresh();
    }

    public function delete(DentalLab $lab): void
    {
        $lab->delete();
    }

    public function replaceServices(DentalLab $lab, array $services): void
    {
        $lab->services()->delete();

        $rows = collect($services)
            ->filter(fn($service) => is_array($service) && !empty($service['name'] ?? null))
            ->map(fn(array $service) => [
                'lab_id' => $lab->id,
                'name' => $service['name'],
                'price' => $service['price'] ?? 0,
                'turnaround_days' => $service['turnaround_days'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            $lab->services()->insert($rows);
        }
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        return DentalLab::query()->whereIn('id', $ids)->update(['status' => $status]);
    }

    public function bulkDelete(array $ids): int
    {
        return DentalLab::query()->whereIn('id', $ids)->delete();
    }

    public function findByIds(array $ids)
    {
        return DentalLab::query()->whereIn('id', $ids)->get();
    }
}
