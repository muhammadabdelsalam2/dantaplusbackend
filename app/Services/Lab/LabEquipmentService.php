<?php

namespace App\Services\Lab;

use App\Models\LabEquipment;
use App\Repositories\LabEquipmentRepository;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LabEquipmentService
{
    public function __construct(
        private LabEquipmentRepository $labEquipmentRepository
    ) {
    }

    public function index(array $filters): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $rows = $this->labEquipmentRepository->paginateForLab((int) $authUser->lab_id, $filters, $perPage);

        $items = collect($rows->items())
            ->map(fn (LabEquipment $equipment) => $this->mapListItem($equipment))
            ->values()
            ->all();

        return ServiceResult::success([
            'items' => $items,
            'summary' => $this->labEquipmentRepository->maintenanceSummaryForLab((int) $authUser->lab_id),
            'filters' => [
                'maintenance_statuses' => LabEquipment::MAINTENANCE_STATUSES,
                'maintenance_status_options' => [
                    ['key' => 'all', 'label' => 'All'],
                    ['key' => LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE, 'label' => LabEquipment::MAINTENANCE_STATUS_UP_TO_DATE],
                    ['key' => LabEquipment::MAINTENANCE_STATUS_KEY_DUE_SOON, 'label' => LabEquipment::MAINTENANCE_STATUS_DUE_SOON],
                    ['key' => LabEquipment::MAINTENANCE_STATUS_KEY_OVERDUE, 'label' => LabEquipment::MAINTENANCE_STATUS_OVERDUE],
                    ['key' => LabEquipment::MAINTENANCE_STATUS_KEY_NA, 'label' => LabEquipment::MAINTENANCE_STATUS_NA],
                ],
                'equipment_statuses' => LabEquipment::STATUSES,
            ],
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 'Equipments fetched successfully');
    }

    public function store(array $data): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $data) {
            $equipment = $this->labEquipmentRepository->create([
                'lab_id' => (int) $authUser->lab_id,
                'name' => $data['name'],
                'model_serial_number' => $data['model_serial_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'last_maintenance_date' => $data['last_maintenance_date'],
                'next_due_date' => Carbon::parse($data['last_maintenance_date'])->addDays((int) $data['maintenance_cycle_days'])->toDateString(),
                'maintenance_status' => LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE,
                'maintenance_cycle_days' => $data['maintenance_cycle_days'],
                'status' => $data['status'] ?? LabEquipment::STATUS_OPERATIONAL,
                'maintenance_notes' => $data['maintenance_notes'] ?? null,
            ]);

            return ServiceResult::success(
                $this->mapDetails($equipment),
                'Equipment created successfully',
                201
            );
        });
    }

    public function show(int $id): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $equipment = $this->labEquipmentRepository->findForLabById((int) $authUser->lab_id, $id);

        if (! $equipment) {
            return ServiceResult::error('Equipment not found.', null, null, 404);
        }

        return ServiceResult::success(
            $this->mapDetails($equipment),
            'Equipment fetched successfully'
        );
    }

    public function update(int $id, array $data): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $id, $data) {
            $equipment = $this->labEquipmentRepository->findForLabById((int) $authUser->lab_id, $id);

            if (! $equipment) {
                return ServiceResult::error('Equipment not found.', null, null, 404);
            }

            $updated = $this->labEquipmentRepository->update($equipment, $data);

            return ServiceResult::success(
                $this->mapDetails($updated),
                'Equipment updated successfully'
            );
        });
    }

    public function destroy(int $id): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $id) {
            $equipment = $this->labEquipmentRepository->findForLabById((int) $authUser->lab_id, $id);

            if (! $equipment) {
                return ServiceResult::error('Equipment not found.', null, null, 404);
            }

            $this->labEquipmentRepository->delete($equipment);

            return ServiceResult::success(null, 'Equipment deleted successfully');
        });
    }

    public function recordMaintenance(int $id, array $data): array
    {
        $authUser = auth()->user();

        if (! $authUser || ! $authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $id, $data) {
            $equipment = $this->labEquipmentRepository->findForLabById((int) $authUser->lab_id, $id);

            if (! $equipment) {
                return ServiceResult::error('Equipment not found.', null, null, 404);
            }

            $payload = [
                'last_maintenance_date' => $data['maintenance_date'] ?? now()->toDateString(),
                'next_due_date' => $data['next_due_date'] ?? $this->nextDueDate($data['maintenance_date'] ?? now()->toDateString(), $equipment),
                'maintenance_status' => LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE,
                'status' => $data['status'] ?? LabEquipment::STATUS_OPERATIONAL,
            ];

            if (array_key_exists('maintenance_notes', $data)) {
                $payload['maintenance_notes'] = $data['maintenance_notes'];
            }

            $updated = $this->labEquipmentRepository->update($equipment, $payload);

            $this->labEquipmentRepository->createMaintenanceLog($updated, [
                'performed_by' => $authUser->id,
                'notes' => $data['maintenance_notes'] ?? $data['notes'] ?? null,
                'maintenance_date' => $payload['last_maintenance_date'],
                'next_due_date' => $payload['next_due_date'],
            ]);

            return ServiceResult::success(
                $this->mapDetails($updated->fresh(['maintenanceLogs.performer:id,name'])),
                'Equipment maintenance recorded successfully'
            );
        });
    }

    private function mapListItem(LabEquipment $equipment): array
    {
        $computed = $this->computeMaintenanceData($equipment);

        return [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'model_serial_number' => $equipment->model_serial_number,
            'purchase_date' => optional($equipment->purchase_date)->format('Y-m-d'),
            'last_maintenance_date' => optional($equipment->last_maintenance_date)->format('Y-m-d'),
            'next_due_date' => $computed['next_due_date'],
            'days_until_maintenance' => $computed['days_until_maintenance'],
            'is_maintenance_due_soon' => $computed['is_maintenance_due_soon'],
            'maintenance_warning' => $computed['maintenance_warning'],
            'maintenance_status' => $computed['maintenance_status'],
            'maintenance_status_key' => $computed['maintenance_status_key'],
            'status' => $equipment->status,
            'maintenance_cycle_days' => $equipment->maintenance_cycle_days,
            'maintenance_notes' => $equipment->maintenance_notes,
        ];
    }

    private function mapDetails(LabEquipment $equipment): array
    {
        $computed = $this->computeMaintenanceData($equipment);

        return [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'model_serial_number' => $equipment->model_serial_number,
            'purchase_date' => optional($equipment->purchase_date)->format('Y-m-d'),
            'last_maintenance_date' => optional($equipment->last_maintenance_date)->format('Y-m-d'),
            'next_due_date' => $computed['next_due_date'],
            'days_until_maintenance' => $computed['days_until_maintenance'],
            'is_maintenance_due_soon' => $computed['is_maintenance_due_soon'],
            'maintenance_warning' => $computed['maintenance_warning'],
            'maintenance_status' => $computed['maintenance_status'],
            'maintenance_status_key' => $computed['maintenance_status_key'],
            'status' => $equipment->status,
            'maintenance_cycle_days' => $equipment->maintenance_cycle_days,
            'maintenance_notes' => $equipment->maintenance_notes,
            'maintenance_logs' => $equipment->relationLoaded('maintenanceLogs')
                ? $equipment->maintenanceLogs->sortByDesc('created_at')->values()->map(fn ($log) => [
                    'id' => $log->id,
                    'performed_by' => $log->performer ? [
                        'id' => $log->performer->id,
                        'name' => $log->performer->name,
                    ] : null,
                    'notes' => $log->notes,
                    'maintenance_date' => optional($log->maintenance_date)->format('Y-m-d'),
                    'next_due_date' => optional($log->next_due_date)->format('Y-m-d'),
                    'created_at' => optional($log->created_at)->toISOString(),
                ])->all()
                : [],
            'created_at' => optional($equipment->created_at)->toISOString(),
            'updated_at' => optional($equipment->updated_at)->toISOString(),
        ];
    }

    private function computeMaintenanceData(LabEquipment $equipment): array
    {
        $nextDue = $equipment->next_due_date
            ? Carbon::parse($equipment->next_due_date)->startOfDay()
            : null;

        if (! $nextDue) {
            $baseDate = $equipment->last_maintenance_date ?? $equipment->purchase_date;

            if ($baseDate) {
                $nextDue = Carbon::parse($baseDate)
                    ->addDays((int) ($equipment->maintenance_cycle_days ?: 30))
                    ->startOfDay();
            }
        }

        if (! $nextDue) {
            return [
                'next_due_date' => null,
                'maintenance_status' => LabEquipment::MAINTENANCE_STATUS_NA,
                'maintenance_status_key' => LabEquipment::MAINTENANCE_STATUS_KEY_NA,
                'days_until_maintenance' => null,
                'is_maintenance_due_soon' => false,
                'maintenance_warning' => null,
            ];
        }

        $today = now()->startOfDay();
        $daysUntil = (int) $today->diffInDays($nextDue, false);

        if ($nextDue->lt($today)) {
            $maintenanceStatus = LabEquipment::MAINTENANCE_STATUS_OVERDUE;
            $maintenanceStatusKey = LabEquipment::MAINTENANCE_STATUS_KEY_OVERDUE;
            $maintenanceWarning = 'overdue';
        } elseif ($nextDue->lte((clone $today)->addDays(14))) {
            $maintenanceStatus = LabEquipment::MAINTENANCE_STATUS_DUE_SOON;
            $maintenanceStatusKey = LabEquipment::MAINTENANCE_STATUS_KEY_DUE_SOON;
            $maintenanceWarning = $daysUntil === 0 ? 'due_today' : "due_in_{$daysUntil}_days";
        } else {
            $maintenanceStatus = LabEquipment::MAINTENANCE_STATUS_UP_TO_DATE;
            $maintenanceStatusKey = LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE;
            $maintenanceWarning = null;
        }

        return [
            'next_due_date' => $nextDue->format('Y-m-d'),
            'maintenance_status' => $maintenanceStatus,
            'maintenance_status_key' => $maintenanceStatusKey,
            'days_until_maintenance' => $daysUntil,
            'is_maintenance_due_soon' => $daysUntil >= 0 && $daysUntil <= 14,
            'maintenance_warning' => $maintenanceWarning,
        ];
    }

    private function nextDueDate(string $maintenanceDate, LabEquipment $equipment): string
    {
        return Carbon::parse($maintenanceDate)
            ->addDays((int) ($equipment->maintenance_cycle_days ?: 30))
            ->toDateString();
    }
}
