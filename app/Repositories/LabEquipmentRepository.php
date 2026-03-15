<?php

namespace App\Repositories;

use App\Models\LabEquipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LabEquipmentRepository
{
    public function paginateForLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters(
            LabEquipment::query()->where('lab_id', $labId),
            $filters
        )
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForLabById(int $labId, int $id): ?LabEquipment
    {
        return LabEquipment::query()
            ->where('lab_id', $labId)
            ->find($id);
    }

    public function create(array $data): LabEquipment
    {
        return LabEquipment::create($data);
    }

    public function update(LabEquipment $equipment, array $data): LabEquipment
    {
        $equipment->update($data);

        return $equipment->refresh();
    }

    public function delete(LabEquipment $equipment): void
    {
        $equipment->delete();
    }

    public function maintenanceSummaryForLab(int $labId): array
    {
        $baseDateSql = "COALESCE(last_maintenance_date, purchase_date)";
        $nextDueSql = "DATE_ADD({$baseDateSql}, INTERVAL maintenance_cycle_days DAY)";

        $overdue = LabEquipment::query()
            ->where('lab_id', $labId)
            ->whereNotNull(DB::raw($baseDateSql))
            ->whereRaw("{$nextDueSql} < CURDATE()")
            ->count();

        $dueSoon = LabEquipment::query()
            ->where('lab_id', $labId)
            ->whereNotNull(DB::raw($baseDateSql))
            ->whereRaw("{$nextDueSql} >= CURDATE()")
            ->whereRaw("{$nextDueSql} <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")
            ->count();

        return [
            'overdue_count' => $overdue,
            'due_soon_count' => $dueSoon,
            'requiring_maintenance_attention_count' => $overdue + $dueSoon,
        ];
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $baseDateSql = "COALESCE(last_maintenance_date, purchase_date)";
        $nextDueSql = "DATE_ADD({$baseDateSql}, INTERVAL maintenance_cycle_days DAY)";

        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('model_serial_number', 'like', "%{$search}%")
                        ->orWhere('maintenance_notes', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['maintenance_status'] ?? null, function (Builder $query, string $maintenanceStatus) use ($baseDateSql, $nextDueSql) {
                if ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_OVERDUE) {
                    $query->whereNotNull(DB::raw($baseDateSql))
                        ->whereRaw("{$nextDueSql} < CURDATE()");
                } elseif ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_DUE_SOON) {
                    $query->whereNotNull(DB::raw($baseDateSql))
                        ->whereRaw("{$nextDueSql} >= CURDATE()")
                        ->whereRaw("{$nextDueSql} <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                } elseif ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_UP_TO_DATE) {
                    $query->where(function (Builder $q) use ($baseDateSql, $nextDueSql) {
                        $q->whereNull(DB::raw($baseDateSql))
                            ->orWhereRaw("{$nextDueSql} > DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                    });
                }
            });
    }
}
