<?php

namespace App\Repositories;

use App\Models\CaseActivityLog;
use App\Models\CaseAttachment;
use App\Models\CaseModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CaseRepository
{
    public function paginateForLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return CaseModel::query()
            ->with([
                'clinic:id,name',
                'lab:id,name',
                'patient:id,user_id',
                'patient.user:id,name',
                'dentist:id,user_id',
                'dentist.user:id,name',
                'technician:id,name,avatar_url',
                'deliveryRep:id,name',
            ])
            ->where('lab_id', $labId)

            ->when($this->activeFilter($filters['status'] ?? null, ['All Statuses', 'all', 'All']),
                fn (Builder $q, $status) => $q->where('status', $status)
            )

            ->when($this->activeFilter($filters['priority'] ?? null, ['All Priorities', 'all', 'All']),
                fn (Builder $q, $priority) => $q->where('priority', $priority)
            )

            ->when($filters['clinic_id'] ?? $filters['clinic'] ?? null,
                function (Builder $q, $clinic) {
                    if (is_numeric($clinic)) {
                        $q->where('clinic_id', $clinic);
                    } elseif (! in_array($clinic, ['All Clinics', 'all', 'All'], true)) {
                        $q->whereHas('clinic', fn (Builder $clinicQuery) => $clinicQuery->where('name', 'like', "%{$clinic}%"));
                    }
                }
            )

            ->when($filters['patient_id'] ?? null,
                fn (Builder $q, $patientId) => $q->where('patient_id', $patientId)
            )

            ->when($filters['dentist_id'] ?? null,
                fn (Builder $q, $dentistId) => $q->where('dentist_id', $dentistId)
            )

            ->when($filters['restricted_user_id'] ?? null, function (Builder $q, $userId) {
                $q->where(function (Builder $restricted) use ($userId) {
                    $restricted->where('assigned_technician_id', $userId)
                        ->orWhere('created_by', $userId);
                });
            })

            ->when($filters['from'] ?? null,
                fn (Builder $q, $from) => $q->whereDate('due_date', '>=', $from)
            )

            ->when($filters['to'] ?? null,
                fn (Builder $q, $to) => $q->whereDate('due_date', '<=', $to)
            )

            ->when($filters['search'] ?? null, function (Builder $q, $search) {

                $q->where(function (Builder $query) use ($search) {

                    $query->where('case_number', 'like', "%{$search}%")
                        ->orWhere('case_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")

                        ->orWhereHas('clinic', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('patient.user', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('dentist.user', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('technician', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })

            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function statsForLab(int $labId, array $filters = []): array
    {
        $base = CaseModel::query()->where('lab_id', $labId);

        if ($restrictedUserId = $filters['restricted_user_id'] ?? null) {
            $base->where(function (Builder $restricted) use ($restrictedUserId) {
                $restricted->where('assigned_technician_id', $restrictedUserId)
                    ->orWhere('created_by', $restrictedUserId);
            });
        }

        return [
            ['key' => 'total', 'label' => 'Total Cases', 'value' => (int) (clone $base)->count()],
            ['key' => 'pending', 'label' => 'Pending Cases', 'value' => (int) (clone $base)->where('status', CaseModel::STATUS_PENDING)->count()],
            ['key' => 'completed', 'label' => 'Completed Cases', 'value' => (int) (clone $base)->where('status', CaseModel::STATUS_COMPLETED)->count()],
            ['key' => 'urgent', 'label' => 'Urgent Cases', 'value' => (int) (clone $base)->where('priority', CaseModel::PRIORITY_URGENT)->count()],
        ];
    }

    public function findByIdForLab(int $id, int $labId): ?CaseModel
    {
        return CaseModel::query()
            ->with([
                'clinic:id,name',
                'lab:id,name',
                'patient:id,user_id',
                'patient.user:id,name',
                'dentist:id,user_id',
                'dentist.user:id,name',
                'technician:id,name,avatar_url',
                'deliveryRep:id,name',
                'creator:id,name',
                'attachments',
            ])
            ->where('lab_id', $labId)
            ->find($id);
    }

    public function create(array $data): CaseModel
    {
        return CaseModel::query()->create($data);
    }

    public function update(CaseModel $case, array $data): CaseModel
    {
        $case->update($data);
        return $case->refresh();
    }

    public function createAttachment(CaseModel $case, array $data): CaseAttachment
    {
        return $case->attachments()->create($data);
    }

    public function createActivityLog(CaseModel $case, array $data): CaseActivityLog
    {
        return $case->activityLogs()->create($data);
    }

    public function listActivityLogs(int $caseId, ?string $range = null)
    {
        return CaseActivityLog::query()
            ->where('case_id', $caseId)
            ->when($range === 'today', fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->when($range === 'week', fn ($q) => $q->where('created_at', '>=', now()->subWeek()))
            ->when($range === 'month', fn ($q) => $q->where('created_at', '>=', now()->subMonth()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function activeFilter(mixed $value, array $emptyValues): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($value, $emptyValues, true) ? null : $value;
    }
}
