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
                'technician:id,name',
                'deliveryRep:id,name',
            ])
            ->where('lab_id', $labId)
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters['clinic_id'] ?? null, fn (Builder $q, $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['patient_id'] ?? null, fn (Builder $q, $patientId) => $q->where('patient_id', $patientId))
            ->when($filters['dentist_id'] ?? null, fn (Builder $q, $dentistId) => $q->where('dentist_id', $dentistId))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('due_date', '<=', $to))
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $q->where(function (Builder $query) use ($search) {
                    $query->where('case_number', 'like', "%{$search}%")
                        ->orWhere('case_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
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
                'technician:id,name',
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

    public function listActivityLogs(int $caseId)
    {
        return CaseActivityLog::query()
            ->where('case_id', $caseId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
