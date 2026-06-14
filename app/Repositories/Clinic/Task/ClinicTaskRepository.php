<?php

namespace App\Repositories\Clinic\Task;

use App\Models\ClinicTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClinicTaskRepository implements ClinicTaskRepositoryInterface
{
    // =========================================================
    // paginate - مع search و priority و status وكل الفلاتر
    // =========================================================
    public function paginate(int $clinicId, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return ClinicTask::query()
->with(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name'])
            ->where('clinic_id', $clinicId)
            // ← جديد: search على title و description
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                           ->orWhere('description', 'like', "%{$search}%");
                });
            })
            // ← جديد: فلتر priority
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            // موجود من قبل
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['assignee_id'] ?? null, function ($q, $assigneeId) {
                $q->where(function ($nested) use ($assigneeId) {
                    $nested->where('assign_to_user_id', $assigneeId)
                           ->orWhere('assign_to_doctor_id', $assigneeId);
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForClinic(int $clinicId, int $taskId): ?ClinicTask
    {
        return ClinicTask::query()
->with(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name'])
            ->where('clinic_id', $clinicId)
            ->find($taskId);
    }

    public function create(array $data): ClinicTask
    {
        return ClinicTask::query()->create($data);
    }

    public function update(ClinicTask $task, array $data): ClinicTask
    {
        $task->update($data);
        return $task->fresh(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name']);
    }

    public function delete(ClinicTask $task): void
    {
        $task->delete();
    }

    public function createReply(ClinicTask $task, array $data): mixed
    {
        return $task->replies()->create($data);
    }
}
