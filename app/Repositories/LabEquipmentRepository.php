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
            ->with(['maintenanceLogs.performer:id,name'])
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

    public function createMaintenanceLog(LabEquipment $equipment, array $data): \App\Models\EquipmentMaintenanceLog
    {
        return $equipment->maintenanceLogs()->create($data);
    }

    public function maintenanceSummaryForLab(int $labId): array
    {
        $baseDateSql = "COALESCE(last_maintenance_date, purchase_date)";
        $nextDueSql = "COALESCE(next_due_date, DATE_ADD({$baseDateSql}, INTERVAL COALESCE(maintenance_cycle_days, 30) DAY))";

        $overdue = LabEquipment::query()
            ->where('lab_id', $labId)
            ->whereRaw("{$nextDueSql} IS NOT NULL")
            ->whereRaw("{$nextDueSql} < CURDATE()")
            ->count();

        $dueSoon = LabEquipment::query()
            ->where('lab_id', $labId)
            ->whereRaw("{$nextDueSql} IS NOT NULL")
            ->whereRaw("{$nextDueSql} >= CURDATE()")
            ->whereRaw("{$nextDueSql} <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)")
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
        $nextDueSql = "COALESCE(next_due_date, DATE_ADD({$baseDateSql}, INTERVAL COALESCE(maintenance_cycle_days, 30) DAY))";

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
                if ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_KEY_OVERDUE) {
                    $query->whereRaw("{$nextDueSql} IS NOT NULL")
                        ->whereRaw("{$nextDueSql} < CURDATE()");
                } elseif ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_KEY_DUE_SOON) {
                    $query->whereRaw("{$nextDueSql} IS NOT NULL")
                        ->whereRaw("{$nextDueSql} >= CURDATE()")
                        ->whereRaw("{$nextDueSql} <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
                } elseif ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE) {
                    $query->whereRaw("{$nextDueSql} > DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
                } elseif ($maintenanceStatus === LabEquipment::MAINTENANCE_STATUS_KEY_NA) {
                    $query->whereNull('next_due_date')
                        ->whereNull('last_maintenance_date')
                        ->whereNull('purchase_date');
                }
            });
    }
}
